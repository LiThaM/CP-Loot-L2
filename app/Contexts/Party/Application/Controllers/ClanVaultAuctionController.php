<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanVaultAuction;
use App\Contexts\Party\Domain\Models\ClanVaultAuctionBid;
use App\Contexts\Party\Domain\Models\ClanVaultItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClanVaultAuctionController extends Controller
{
    public function bid(Request $request, ClanVaultAuction $auction): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');

        $auctionClanId = $auction->vaultItem?->clan_id;
        abort_unless($auctionClanId === $clan->id, 403, 'Esta subasta no pertenece a tu clan.');
        abort_unless($auction->status === ClanVaultAuction::STATUS_OPEN, 422, 'La subasta no está abierta.');
        abort_unless($auction->ends_at->isFuture(), 422, 'La subasta ya ha terminado.');

        $data = $request->validate([
            'bid_amount' => ['required', 'integer', 'min:' . $auction->min_bid],
        ]);

        $currentMax = ClanVaultAuctionBid::where('auction_id', $auction->id)
            ->max('bid_amount') ?? 0;

        if ($data['bid_amount'] <= $currentMax) {
            return back()->with('error', 'Tu puja debe ser mayor que la puja actual (' . $currentMax . ').');
        }

        ClanVaultAuctionBid::updateOrCreate(
            ['auction_id' => $auction->id, 'user_id' => $user->id],
            ['bid_amount' => $data['bid_amount']]
        );

        return back()->with('success', 'Puja registrada.');
    }

    public function close(Request $request, ClanVaultAuction $auction): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');

        $item = $auction->vaultItem;
        abort_unless($item && $item->clan_id === $clan->id, 403, 'Esta subasta no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden cerrar subastas.');
        abort_unless($auction->status === ClanVaultAuction::STATUS_OPEN, 422, 'La subasta no está abierta.');

        DB::transaction(function () use ($auction, $item, $user) {
            $topBid = ClanVaultAuctionBid::where('auction_id', $auction->id)
                ->orderByDesc('bid_amount')
                ->first();

            if ($topBid) {
                $auction->winner_user_id = $topBid->user_id;
                $auction->winning_bid    = $topBid->bid_amount;
                $auction->status         = ClanVaultAuction::STATUS_CLOSED;
                $auction->closed_by_user_id = $user->id;
                $auction->save();

                $item->status = ClanVaultItem::STATUS_ASSIGNED;
                $item->assigned_to_cp_id = null; // winner is a user, not necessarily a CP
                $item->save();
            } else {
                // No bids — cancel and return to vault
                $auction->status = ClanVaultAuction::STATUS_CLOSED;
                $auction->closed_by_user_id = $user->id;
                $auction->save();

                $item->status = ClanVaultItem::STATUS_IN_VAULT;
                $item->save();
            }
        });

        return back()->with('success', 'Subasta cerrada.');
    }

    public function cancel(Request $request, ClanVaultAuction $auction): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');

        $item = $auction->vaultItem;
        abort_unless($item && $item->clan_id === $clan->id, 403, 'Esta subasta no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden cancelar subastas.');
        abort_unless($auction->status === ClanVaultAuction::STATUS_OPEN, 422, 'La subasta no está abierta.');

        DB::transaction(function () use ($auction, $item) {
            $auction->status = ClanVaultAuction::STATUS_CANCELLED;
            $auction->save();

            $item->status = ClanVaultItem::STATUS_IN_VAULT;
            $item->save();
        });

        return back()->with('success', 'Subasta cancelada. El item vuelve al cofre.');
    }
}
