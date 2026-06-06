<?php

namespace App\Contexts\Party\Application\Services;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Domain\Models\TrackerContribution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Derives DKP-style point contributions from confirmed `LootReport`s
 * when the owning CP has the value-based tracker turned on. Each
 * `LootEntry` produces N rows (one per attendee earning a share) with
 * `points = effective_price * amount / cp.tracker_divisor`.
 *
 * The composite unique on `(source_loot_entry_id, user_id)` absorbs
 * re-confirmations idempotently.
 *
 * Event types that represent internal warehouse movements (SELL,
 * ASSIGN, WAREHOUSE_*, RETURN) are deliberately ignored — they redistribute
 * existing CP inventory rather than introducing new loot value.
 */
class TrackerContributionService
{
    private const INTERNAL_EVENT_TYPES = [
        'SELL',
        'ASSIGN',
        'WAREHOUSE_CRAFT_CONSUME',
        'WAREHOUSE_RECHECK_LOSS',
        'WAREHOUSE_RECHECK_GAIN',
        'RETURN',
    ];

    public function recordFromReport(LootReport $report): void
    {
        $cp = $report->cp;
        if (! $cp || ! $cp->tracker_enabled || ! $cp->tracker_divisor) {
            return;
        }
        // Time gate: ignore reports created before the CP opted into the
        // tracker, so toggling ON doesn't avalanche months of history.
        if ($cp->tracker_enabled_at && $report->created_at && $report->created_at->lt($cp->tracker_enabled_at)) {
            return;
        }
        if (in_array($report->event_type, self::INTERNAL_EVENT_TYPES, true)) {
            return;
        }
        if ($report->status !== 'confirmed') {
            return;
        }

        $entries = LootEntry::with('item')->where('loot_report_id', $report->id)->get();
        if ($entries->isEmpty()) {
            return;
        }

        $divisor = max(1, (int) $cp->tracker_divisor);

        $attendeeUserIds = $report->attendees()
            ->where('is_external', false)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        if (empty($attendeeUserIds)) {
            return;
        }

        // Filter out banned members up front so a banned attendee doesn't
        // dilute the per-person share.
        $validUserIds = User::whereIn('id', $attendeeUserIds)
            ->where('cp_id', $cp->id)
            ->where('membership_status', '!=', 'banned')
            ->pluck('id')
            ->all();

        if (empty($validUserIds)) {
            return;
        }

        foreach ($entries as $entry) {
            $this->recordFromEntry($entry, $report, $validUserIds, $divisor);
        }
    }

    private function recordFromEntry(LootEntry $entry, LootReport $report, array $validUserIds, int $divisor): void
    {
        $item = $entry->item;
        if (! $item) {
            return;
        }

        // Effective price: user-set market_price wins, npc_sell_price is the
        // floor (same fallback already shown in MarketPriceCell).
        $effectivePrice = $item->market_price ?? $item->npc_sell_price;
        if (! $effectivePrice || $effectivePrice <= 0) {
            return;
        }

        $totalValue = ((int) $effectivePrice) * max(1, (int) ($entry->amount ?? 1));
        $totalPoints = $totalValue / $divisor;

        // If the entry was directly awarded to a single member, the badge
        // is SOLO and that member gets the full points. Otherwise we split
        // across all valid attendees as PARTY/N.
        $awardedTo = $entry->awarded_to;
        $recipients = ($awardedTo && in_array($awardedTo, $validUserIds, true))
            ? [$awardedTo]
            : $validUserIds;

        $n = count($recipients);
        if ($n === 0) {
            return;
        }

        $badge = $n === 1 ? TrackerContribution::BADGE_SOLO : TrackerContribution::BADGE_PARTY_PREFIX.$n;
        $pointsPer = round($totalPoints / $n, 2);
        if ($pointsPer <= 0) {
            return;
        }

        $description = trim($item->name.' x'.$entry->amount);
        $now = now();

        $rows = [];
        foreach ($recipients as $uid) {
            $rows[] = [
                'cp_id' => $report->cp_id,
                'user_id' => $uid,
                'type' => TrackerContribution::TYPE_MATERIAL,
                'points' => $pointsPer,
                'description' => $description,
                'badge' => $badge,
                'source_loot_entry_id' => $entry->id,
                'created_by_user_id' => $report->requested_by_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            // Idempotency: the unique (source_loot_entry_id, user_id) index
            // means re-confirming the same report won't double-credit.
            DB::table('tracker_contributions')->insertOrIgnore($rows);
        } catch (QueryException $e) {
            Log::warning('TrackerContributionService failed', [
                'loot_entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
