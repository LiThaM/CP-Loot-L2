<?php

namespace App\Contexts\Party\Application\Services;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\CpAuction;
use App\Contexts\Party\Domain\Models\CpAuctionBid;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\Party\Domain\Models\TrackerContribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Auction lifecycle for CP warehouse items.
 *
 * Flow:
 *   open()    leader opens an auction and locks N units out of the
 *             warehouse via a WAREHOUSE_RECHECK_LOSS report.
 *   bid()     any member of the CP places a bid > current_bid. If the
 *             auction has a buy_now_price and the bid >= buy_now, the
 *             auction closes immediately. No escrow — `available`
 *             at validation time = total balance - current commitments
 *             on other open auctions where the user is the leading bid.
 *   close()   called by the cron at ends_at, or immediately on buy_now.
 *             Sets winner_id if there was a bidder; otherwise marks
 *             cancelled and returns the item to the warehouse.
 *   fulfill() the leader hands the item to the winner. Charges the
 *             winning bid (negative TrackerContribution for points, or
 *             PointsLog action_type='AUCTION_PAYOUT' for adena) and
 *             generates an ASSIGN LootReport. Idempotent.
 *   cancel()  leader-initiated abort while open or after close pre-fulfill.
 *             Returns the reserved item to the warehouse via a
 *             WAREHOUSE_RECHECK_GAIN report.
 */
class AuctionService
{
    public function open(
        User $leader,
        Item $item,
        int $amount,
        string $currency,
        float $startingBid,
        ?float $buyNow,
        Carbon $endsAt,
    ): CpAuction {
        $cp = $leader->cp;
        if (! $cp) {
            throw new RuntimeException('Leader has no CP.');
        }
        if (! in_array($currency, [CpAuction::CURRENCY_POINTS, CpAuction::CURRENCY_ADENA], true)) {
            throw new RuntimeException('Invalid currency.');
        }
        if ($currency === CpAuction::CURRENCY_POINTS && ! $cp->tracker_enabled) {
            throw new RuntimeException('Points auctions require tracker_enabled on this CP.');
        }
        if ($amount < 1) {
            throw new RuntimeException('Amount must be >= 1.');
        }
        if ($startingBid <= 0) {
            throw new RuntimeException('Starting bid must be > 0.');
        }
        if ($buyNow !== null && $buyNow < $startingBid) {
            throw new RuntimeException('Buy now must be >= starting bid.');
        }
        if ($endsAt->lte(now()->addMinutes(1))) {
            throw new RuntimeException('ends_at must be at least 1 minute in the future.');
        }

        // Stock check + reservation. Available = incoming - outgoing for
        // this item in the CP, same aggregation used elsewhere in the app.
        $available = $this->vaultAvailable($cp->id, $item->id);
        if ($available < $amount) {
            throw new RuntimeException('Insufficient stock in the warehouse. Available: '.$available);
        }

        return DB::transaction(function () use ($cp, $leader, $item, $amount, $currency, $startingBid, $buyNow, $endsAt) {
            // Reservation report — pulls the item out of the warehouse
            // counts until cancel returns it. Skipped event_type by the
            // tracker (it's in INTERNAL_EVENT_TYPES) so no DKP movement.
            $reservation = LootReport::create([
                'cp_id' => $cp->id,
                'requested_by_id' => $leader->id,
                'event_type' => 'WAREHOUSE_RECHECK_LOSS',
                'status' => 'confirmed',
                'description' => 'Auction reservation for '.$item->name,
            ]);
            LootEntry::create([
                'loot_report_id' => $reservation->id,
                'item_id' => $item->id,
                'awarded_to' => null,
                'amount' => $amount,
            ]);

            return CpAuction::create([
                'cp_id' => $cp->id,
                'item_id' => $item->id,
                'amount' => $amount,
                'currency' => $currency,
                'starting_bid' => $startingBid,
                'buy_now_price' => $buyNow,
                'current_bid' => null,
                'current_bidder_id' => null,
                'ends_at' => $endsAt,
                'status' => CpAuction::STATUS_OPEN,
                'created_by_user_id' => $leader->id,
                'reservation_report_id' => $reservation->id,
            ]);
        });
    }

    public function bid(User $member, CpAuction $auction, float $amount): CpAuctionBid
    {
        if ($auction->status !== CpAuction::STATUS_OPEN) {
            throw new RuntimeException('Auction is not open.');
        }
        if ($auction->ends_at && $auction->ends_at->lte(now())) {
            throw new RuntimeException('Auction has already expired.');
        }
        if ($member->cp_id !== $auction->cp_id) {
            throw new RuntimeException('You are not a member of this CP.');
        }

        $minBid = $auction->current_bid !== null
            ? (float) $auction->current_bid + 0.01
            : (float) $auction->starting_bid;
        if ($amount < $minBid) {
            throw new RuntimeException('Bid must be at least '.$minBid.'.');
        }

        // Available check — total balance minus current commitments on
        // OTHER open auctions where this user is the leading bidder. We
        // don't lock points; if someone outbids them they're free again.
        $available = $this->availableBalance($member, $auction->cp_id, $auction->currency, excludeAuctionId: $auction->id);
        if ($available < $amount) {
            throw new RuntimeException('Insufficient balance. Available: '.number_format($available, 2));
        }

        return DB::transaction(function () use ($member, $auction, $amount) {
            $bid = CpAuctionBid::create([
                'auction_id' => $auction->id,
                'user_id' => $member->id,
                'amount' => $amount,
                'placed_at' => now(),
            ]);

            $auction->update([
                'current_bid' => $amount,
                'current_bidder_id' => $member->id,
            ]);

            // Buy-now closes the auction immediately.
            if ($auction->buy_now_price !== null && $amount >= (float) $auction->buy_now_price) {
                $this->close($auction->fresh());
            }

            return $bid;
        });
    }

    public function close(CpAuction $auction): void
    {
        if ($auction->status !== CpAuction::STATUS_OPEN) {
            return;
        }
        if ($auction->current_bidder_id) {
            $auction->update([
                'status' => CpAuction::STATUS_CLOSED,
                'winner_id' => $auction->current_bidder_id,
            ]);
            return;
        }

        // No bidder — refund stock and mark cancelled.
        $this->returnStockToWarehouse($auction);
        $auction->update(['status' => CpAuction::STATUS_CANCELLED]);
    }

    public function fulfill(CpAuction $auction, User $leader): void
    {
        if ($auction->status !== CpAuction::STATUS_CLOSED) {
            throw new RuntimeException('Auction is not closed (current status: '.$auction->status.').');
        }
        if (! $auction->winner_id || ! $auction->current_bid) {
            throw new RuntimeException('Auction has no winner.');
        }
        if ($leader->cp_id !== $auction->cp_id) {
            throw new RuntimeException('You are not a member of this CP.');
        }

        DB::transaction(function () use ($auction, $leader) {
            // Hand over the item via an ASSIGN report so the existing
            // warehouse aggregation accounts for it. The reservation
            // already pulled it out of stock; ASSIGN counts as outgoing
            // too — net effect is "winner gets item, stock-2 from CP
            // accounting": handle by VOIDING the reservation instead, so
            // the chain stays at a single "withdrawal" event.
            if ($auction->reservation_report_id) {
                LootReport::where('id', $auction->reservation_report_id)
                    ->update(['voided_at' => now(), 'voided_by_user_id' => $leader->id, 'voided_reason' => 'Auction fulfilled']);
            }

            $assignReport = LootReport::create([
                'cp_id' => $auction->cp_id,
                'requested_by_id' => $leader->id,
                'event_type' => 'ASSIGN',
                'status' => 'confirmed',
                'description' => 'Auction #'.$auction->id.' fulfilled',
                'recipient_ids' => [$auction->winner_id],
            ]);
            LootEntry::create([
                'loot_report_id' => $assignReport->id,
                'item_id' => $auction->item_id,
                'awarded_to' => $auction->winner_id,
                'amount' => $auction->amount,
            ]);

            // Charge the winner.
            if ($auction->currency === CpAuction::CURRENCY_POINTS) {
                TrackerContribution::create([
                    'cp_id' => $auction->cp_id,
                    'user_id' => $auction->winner_id,
                    'type' => TrackerContribution::TYPE_COST,
                    'points' => -((float) $auction->current_bid),
                    'description' => 'Auction #'.$auction->id.': '.$auction->item->name,
                    'badge' => TrackerContribution::BADGE_AUCTION,
                    'source_loot_entry_id' => null,
                    'created_by_user_id' => $leader->id,
                ]);
            } else {
                PointsLog::create([
                    'cp_id' => $auction->cp_id,
                    'user_id' => $auction->winner_id,
                    'action_type' => 'AUCTION_PAYOUT',
                    'points' => 0,
                    'adena' => -((int) round($auction->current_bid)),
                    'description' => 'Auction #'.$auction->id.': '.$auction->item->name,
                ]);
            }

            $auction->update([
                'status' => CpAuction::STATUS_FULFILLED,
                'fulfilled_at' => now(),
            ]);
        });
    }

    public function cancel(CpAuction $auction, User $leader): void
    {
        if (! in_array($auction->status, [CpAuction::STATUS_OPEN, CpAuction::STATUS_CLOSED], true)) {
            throw new RuntimeException('Auction cannot be cancelled (status: '.$auction->status.').');
        }
        if ($leader->cp_id !== $auction->cp_id) {
            throw new RuntimeException('You are not a member of this CP.');
        }

        DB::transaction(function () use ($auction) {
            $this->returnStockToWarehouse($auction);
            $auction->update(['status' => CpAuction::STATUS_CANCELLED]);
        });
    }

    /**
     * Available balance for a user in a given currency, minus their
     * leading-bid commitments across OTHER open auctions in the same CP.
     */
    public function availableBalance(User $user, int $cpId, string $currency, ?int $excludeAuctionId = null): float
    {
        $base = $currency === CpAuction::CURRENCY_POINTS
            ? (float) DB::table('tracker_contributions')
                ->where('cp_id', $cpId)
                ->where('user_id', $user->id)
                ->sum('points')
            : $this->adenaBalance($cpId, $user->id);

        $commitmentsQuery = DB::table('cp_auctions')
            ->where('cp_id', $cpId)
            ->where('current_bidder_id', $user->id)
            ->where('status', CpAuction::STATUS_OPEN)
            ->where('currency', $currency);

        if ($excludeAuctionId !== null) {
            $commitmentsQuery->where('id', '!=', $excludeAuctionId);
        }

        $commitments = (float) $commitmentsQuery->sum('current_bid');
        return $base - $commitments;
    }

    private function adenaBalance(int $cpId, int $userId): float
    {
        $gain = (int) PointsLog::where('cp_id', $cpId)
            ->where('user_id', $userId)
            ->where('action_type', 'ADENA_GAIN')
            ->sum('adena');
        $out = abs((int) PointsLog::where('cp_id', $cpId)
            ->where('user_id', $userId)
            ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET', 'AUCTION_PAYOUT'])
            ->sum('adena'));
        return max(0.0, (float) ($gain - $out));
    }

    private function vaultAvailable(int $cpId, int $itemId): int
    {
        $incoming = (int) DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->where('loot_entries.item_id', $itemId)
            ->sum('loot_entries.amount');

        $outgoing = (int) DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->where('loot_entries.item_id', $itemId)
            ->sum('loot_entries.amount');

        return max(0, $incoming - $outgoing);
    }

    /**
     * Cancel-path: create a WAREHOUSE_RECHECK_GAIN to balance the
     * reservation's WAREHOUSE_RECHECK_LOSS, so the item is visible in the
     * vault again.
     */
    private function returnStockToWarehouse(CpAuction $auction): void
    {
        if (! $auction->item_id || ! $auction->amount) {
            return;
        }
        $returnReport = LootReport::create([
            'cp_id' => $auction->cp_id,
            'requested_by_id' => $auction->created_by_user_id,
            'event_type' => 'WAREHOUSE_RECHECK_GAIN',
            'status' => 'confirmed',
            'description' => 'Auction #'.$auction->id.' cancelled — stock returned',
        ]);
        LootEntry::create([
            'loot_report_id' => $returnReport->id,
            'item_id' => $auction->item_id,
            'awarded_to' => null,
            'amount' => $auction->amount,
        ]);
    }
}
