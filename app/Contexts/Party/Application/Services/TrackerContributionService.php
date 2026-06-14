<?php

namespace App\Contexts\Party\Application\Services;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Domain\Models\ConstParty;
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
    /**
     * Floor (in adena) for the "round up small drops" CP option: a loot
     * entry worth less than this is valued AT this amount before dividing
     * into points, so cheap drops aren't worth a fraction of a point.
     */
    private const ROUND_UP_FLOOR = 1000;

    private const INTERNAL_EVENT_TYPES = [
        'SELL',
        'ASSIGN',
        'WAREHOUSE_CRAFT_CONSUME',
        'WAREHOUSE_RECHECK_LOSS',
        'WAREHOUSE_RECHECK_GAIN',
        'RETURN',
    ];

    /**
     * Records a NEGATIVE tracker contribution per LootEntry of an ASSIGN
     * report. Mirror of `recordFromReport` but for the spend side: the
     * receiver pays `value / divisor` points for each item received. Same
     * idempotency guarantees via the `(source_loot_entry_id, user_id)`
     * unique index. Internal report types like ASSIGN are NOT processed
     * by recordFromReport (they're in INTERNAL_EVENT_TYPES) — this method
     * is the explicit hook for the spend side.
     */
    public function recordAssignmentCost(LootReport $report): void
    {
        if ($report->event_type !== 'ASSIGN') {
            return;
        }
        $cp = $report->cp;
        if (! $cp || ! $cp->tracker_enabled || ! $cp->tracker_divisor) {
            return;
        }
        if ($cp->tracker_enabled_at && $report->created_at && $report->created_at->lt($cp->tracker_enabled_at)) {
            return;
        }

        $entries = LootEntry::with('item')->where('loot_report_id', $report->id)->get();
        if ($entries->isEmpty()) {
            return;
        }

        $divisor = max(1, (int) $cp->tracker_divisor);
        $valueByMarket = (bool) ($cp->tracker_value_by_market ?? true);
        $roundUpSmall = (bool) ($cp->tracker_round_up_below_1000 ?? false);
        $roundPointsUp = (bool) ($cp->tracker_round_points_up ?? false);
        $now = now();
        $rows = [];

        foreach ($entries as $entry) {
            $item = $entry->item;
            if (! $item || ! $entry->awarded_to) {
                continue;
            }
            $effectivePrice = $this->effectivePrice($item, $valueByMarket);
            if (! $effectivePrice || $effectivePrice <= 0) {
                continue;
            }
            $totalValue = ((int) $effectivePrice) * max(1, (int) ($entry->amount ?? 1));
            $totalValue = $this->applyMinValue($totalValue, $roundUpSmall);
            $costPoints = $this->roundPoints($totalValue / $divisor, $roundPointsUp);
            if ($costPoints <= 0) {
                continue;
            }

            $description = trim('Assign · ' . $item->name . ' x' . $entry->amount);

            $rows[] = [
                'cp_id' => $report->cp_id,
                'user_id' => $entry->awarded_to,
                'type' => TrackerContribution::TYPE_COST,
                'points' => -$costPoints,
                'description' => $description,
                'badge' => TrackerContribution::BADGE_COST,
                'source_loot_entry_id' => $entry->id,
                'created_by_user_id' => $report->requested_by_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            return;
        }
        try {
            DB::table('tracker_contributions')->insertOrIgnore($rows);
        } catch (QueryException $e) {
            Log::warning('TrackerContributionService::recordAssignmentCost failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

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

        $valueByMarket = (bool) ($cp->tracker_value_by_market ?? true);
        $roundUpSmall = (bool) ($cp->tracker_round_up_below_1000 ?? false);
        $roundPointsUp = (bool) ($cp->tracker_round_points_up ?? false);

        foreach ($entries as $entry) {
            $this->recordFromEntry($entry, $report, $validUserIds, $divisor, $valueByMarket, $roundUpSmall, $roundPointsUp);
        }
    }

    /**
     * Rebuild a CP's automatic (loot-derived) tracker contributions from its
     * confirmed reports using the CURRENT divisor / valuation basis / prices.
     * Manual contributions (source_loot_entry_id null) are kept. Returns the
     * before/after point totals so callers can report the delta.
     *
     * @return array{before_rows:int, before_points:float, after_rows:int, after_points:float}
     */
    public function recomputeCp(ConstParty $cp): array
    {
        $autoTotals = function () use ($cp) {
            $q = DB::table('tracker_contributions')->where('cp_id', $cp->id)->whereNotNull('source_loot_entry_id');

            return ['rows' => (clone $q)->count(), 'points' => round((float) (clone $q)->sum('points'), 2)];
        };

        $before = $autoTotals();

        DB::transaction(function () use ($cp) {
            DB::table('tracker_contributions')
                ->where('cp_id', $cp->id)
                ->whereNotNull('source_loot_entry_id')
                ->delete();

            LootReport::where('cp_id', $cp->id)
                ->where('status', 'confirmed')
                ->orderBy('created_at')
                ->chunkById(200, function ($reports) {
                    foreach ($reports as $report) {
                        $this->recordFromReport($report);
                        $this->recordAssignmentCost($report);
                    }
                });
        });

        $after = $autoTotals();

        return [
            'before_rows' => $before['rows'],
            'before_points' => $before['points'],
            'after_rows' => $after['rows'],
            'after_points' => $after['points'],
        ];
    }

    /**
     * Per-CP valuation rule. When `valueByMarket` the item is priced by its
     * market_price (NPC sell as fallback); otherwise by its NPC sell price
     * (market as fallback). Mirrors the "Value by market price" CP setting.
     */
    private function effectivePrice(?\App\Contexts\Loot\Domain\Models\Item $item, bool $valueByMarket): ?int
    {
        if (! $item) {
            return null;
        }
        $price = $valueByMarket
            ? ($item->market_price ?? $item->npc_sell_price)
            : ($item->npc_sell_price ?? $item->market_price);

        return $price !== null ? (int) $price : null;
    }

    /**
     * "Round up below 1000" CP option: a loot entry whose total value is under
     * ROUND_UP_FLOOR adena is valued at the floor instead. No-op when disabled.
     */
    private function applyMinValue(int $totalValue, bool $roundUpSmall): int
    {
        if ($roundUpSmall && $totalValue > 0 && $totalValue < self::ROUND_UP_FLOOR) {
            return self::ROUND_UP_FLOOR;
        }

        return $totalValue;
    }

    /**
     * "Whole points" CP option: round up to an integer (no decimals) when on,
     * otherwise keep the legacy 2-decimal rounding.
     */
    private function roundPoints(float $points, bool $roundUp): float
    {
        return $roundUp ? (float) ceil($points) : round($points, 2);
    }

    private function recordFromEntry(LootEntry $entry, LootReport $report, array $validUserIds, int $divisor, bool $valueByMarket = true, bool $roundUpSmall = false, bool $roundPointsUp = false): void
    {
        $item = $entry->item;
        if (! $item) {
            return;
        }

        $effectivePrice = $this->effectivePrice($item, $valueByMarket);
        if (! $effectivePrice || $effectivePrice <= 0) {
            return;
        }

        $totalValue = ((int) $effectivePrice) * max(1, (int) ($entry->amount ?? 1));
        $totalValue = $this->applyMinValue($totalValue, $roundUpSmall);
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
        $pointsPer = $this->roundPoints($totalPoints / $n, $roundPointsUp);
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
