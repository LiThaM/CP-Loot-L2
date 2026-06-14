<?php

namespace App\Console\Commands;

use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Application\Services\TrackerContributionService;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-derives a CP's automatic (loot-based) tracker contributions from its
 * confirmed loot reports using the CURRENT item prices and divisor. Use it
 * after importing/adjusting market prices or changing the divisor so the
 * leaderboard reflects today's values.
 *
 * Valuation per item is market_price → npc_sell_price (the existing rule).
 * Manual contributions (events, hand-entered bonuses; source_loot_entry_id
 * is null) are left untouched — only the auto rows are wiped and rebuilt.
 */
class TrackerRecompute extends Command
{
    protected $signature = 'tracker:recompute
        {cp : CP id or (partial) name}
        {--divisor= : set tracker_divisor before recomputing}
        {--dry-run : report the before/after totals without writing}';

    protected $description = "Rebuild a CP's auto loot-derived tracker points from current prices/divisor (keeps manual contributions).";

    public function handle(TrackerContributionService $service): int
    {
        $cpArg = (string) $this->argument('cp');
        $cp = ConstParty::query()
            ->when(ctype_digit($cpArg), fn ($q) => $q->where('id', (int) $cpArg))
            ->when(! ctype_digit($cpArg), fn ($q) => $q->where('name', 'like', '%'.$cpArg.'%'))
            ->first();

        if (! $cp) {
            $this->error("CP not found: {$cpArg}");

            return self::FAILURE;
        }
        if (! $cp->tracker_enabled) {
            $this->warn("Tracker is OFF for {$cp->name} (#{$cp->id}) — recomputing anyway.");
        }

        $dry = (bool) $this->option('dry-run');

        if (($divisorOpt = $this->option('divisor')) !== null) {
            $newDivisor = max(1, (int) $divisorOpt);
            if (! $dry) {
                DB::table('const_parties')->where('id', $cp->id)->update(['tracker_divisor' => $newDivisor]);
            }
            $cp->tracker_divisor = $newDivisor;
            $this->info("tracker_divisor → {$newDivisor}".($dry ? ' (dry-run, not saved)' : ''));
        }

        $autoQuery = fn () => DB::table('tracker_contributions')
            ->where('cp_id', $cp->id)
            ->whereNotNull('source_loot_entry_id');

        $beforeRows = $autoQuery()->count();
        $beforePoints = round((float) $autoQuery()->sum('points'), 2);
        $manualRows = DB::table('tracker_contributions')->where('cp_id', $cp->id)->whereNull('source_loot_entry_id')->count();
        $this->line(sprintf(
            'BEFORE  %s (#%d): auto=%d rows / %s pts · manual kept=%d · divisor=%d',
            $cp->name, $cp->id, $beforeRows, number_format($beforePoints, 2), $manualRows, $cp->tracker_divisor
        ));

        if ($dry) {
            $this->warn('Dry-run: nothing written.');

            return self::SUCCESS;
        }

        $stats = $service->recomputeCp($cp);
        $this->info(sprintf(
            'AFTER   %s (#%d): auto=%d rows / %s pts (was %s)',
            $cp->name, $cp->id, $stats['after_rows'], number_format($stats['after_points'], 2), number_format($stats['before_points'], 2)
        ));

        return self::SUCCESS;
    }
}
