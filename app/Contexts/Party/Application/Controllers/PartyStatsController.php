<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Party\Domain\Models\TrackerContribution;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deep-dive analytics for a single CP. Centralizes aggregations that
 * were previously scattered between the dashboard, the party page, and
 * nothing at all (top items, adena flow timeline, member activity
 * heatmap). Read-only for every member of the CP.
 *
 * All queries scope by `cp_id` and accept a `period` of 7/30/90 days.
 * On-the-fly aggregation — if production reveals it's slow, cache with
 * a 5-minute Redis TTL keyed by `(cp_id, period)`. Not needed yet.
 */
class PartyStatsController extends Controller
{
    private const ALLOWED_PERIODS = [7, 30, 90];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->cp_id, 403);

        $cp = $user->cp;
        abort_unless($cp, 404);

        $period = (int) $request->query('period', 30);
        if (! in_array($period, self::ALLOWED_PERIODS, true)) {
            $period = 30;
        }

        $cpId = $cp->id;
        $now = now();
        $from = $now->copy()->subDays($period - 1)->startOfDay();
        $prevFrom = $from->copy()->subDays($period);
        $prevTo = $from->copy()->subSecond();

        return Inertia::render('Party/Stats', [
            'cp' => [
                'id' => $cp->id,
                'name' => $cp->name,
                'chronicle' => $cp->chronicle,
                'server' => $cp->server,
                'tracker_enabled' => (bool) $cp->tracker_enabled,
            ],
            'period' => $period,
            'periodOptions' => self::ALLOWED_PERIODS,
            'kpis' => $this->kpis($cpId, $from, $now, $prevFrom, $prevTo),
            'reportTrend' => $this->reportTrend($cpId, $from, $now),
            'adenaFlow' => $this->adenaFlow($cpId, $from, $now),
            'topItems' => $this->topItemsDropped($cpId, $from, $now, 10),
            'activityHeatmap' => $this->memberActivityHeatmap($cpId, $from, $now),
            'gradeDistribution' => $this->gradeDistribution($cpId),
            'trackerTop' => $cp->tracker_enabled ? $this->trackerTop($cpId, $from, $now, 5) : null,
            'financialScoreboard' => $this->financialScoreboard($cpId),
        ]);
    }

    private function kpis(int $cpId, Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $reportsNow = DB::table('loot_reports')
            ->where('cp_id', $cpId)
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $reportsPrev = DB::table('loot_reports')
            ->where('cp_id', $cpId)
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->count();

        $adenaIn = (int) DB::table('points_logs')
            ->where('cp_id', $cpId)
            ->where('action_type', 'ADENA_GAIN')
            ->whereBetween('created_at', [$from, $to])
            ->sum('adena');
        $adenaOut = (int) abs((int) DB::table('points_logs')
            ->where('cp_id', $cpId)
            ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
            ->whereBetween('created_at', [$from, $to])
            ->sum('adena'));

        $activeMembers = DB::table('loot_reports')
            ->where('cp_id', $cpId)
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$from, $to])
            ->distinct('requested_by_id')
            ->count('requested_by_id');

        $vaultValue = $this->vaultValue($cpId);

        return [
            'reports' => [
                'value' => $reportsNow,
                'delta' => $reportsNow - $reportsPrev,
                'prev' => $reportsPrev,
            ],
            'adena_in' => $adenaIn,
            'adena_out' => $adenaOut,
            'adena_net' => $adenaIn - $adenaOut,
            'active_members' => $activeMembers,
            'vault_value' => $vaultValue,
        ];
    }

    private function reportTrend(int $cpId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('loot_reports')
            ->selectRaw('DATE(created_at) as d, event_type, COUNT(*) as c')
            ->where('cp_id', $cpId)
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('d', 'event_type')
            ->get();

        $eventTypes = ['FARM', 'BOSS', 'EPIC', 'SIEGE'];
        $labels = [];
        $series = array_fill_keys($eventTypes, []);

        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $label = $cursor->toDateString();
            $labels[] = $label;
            foreach ($eventTypes as $et) {
                $match = $rows->first(fn ($r) => $r->d === $label && $r->event_type === $et);
                $series[$et][] = (int) ($match->c ?? 0);
            }
            $cursor->addDay();
        }

        return ['labels' => $labels, 'series' => $series];
    }

    private function adenaFlow(int $cpId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('points_logs')
            ->selectRaw('DATE(created_at) as d, action_type, SUM(adena) as s')
            ->where('cp_id', $cpId)
            ->whereIn('action_type', ['ADENA_GAIN', 'ADENA_PAYOUT', 'ADENA_OFFSET'])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('d', 'action_type')
            ->get();

        $labels = [];
        $inSeries = [];
        $outSeries = [];

        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $label = $cursor->toDateString();
            $labels[] = $label;
            $gain = (int) ($rows->first(fn ($r) => $r->d === $label && $r->action_type === 'ADENA_GAIN')->s ?? 0);
            $payout = (int) ($rows->first(fn ($r) => $r->d === $label && $r->action_type === 'ADENA_PAYOUT')->s ?? 0);
            $offset = (int) ($rows->first(fn ($r) => $r->d === $label && $r->action_type === 'ADENA_OFFSET')->s ?? 0);
            $inSeries[] = $gain;
            $outSeries[] = abs($payout) + abs($offset);
            $cursor->addDay();
        }

        return ['labels' => $labels, 'in' => $inSeries, 'out' => $outSeries];
    }

    private function topItemsDropped(int $cpId, Carbon $from, Carbon $to, int $limit): array
    {
        return DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNotIn('loot_reports.event_type', ['SELL', 'ASSIGN', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS', 'WAREHOUSE_RECHECK_GAIN', 'RETURN'])
            ->whereBetween('loot_reports.created_at', [$from, $to])
            ->groupBy('items.id', 'items.name', 'items.image_url', 'items.grade', 'items.market_price', 'items.npc_sell_price')
            ->orderByDesc(DB::raw('COUNT(loot_entries.id)'))
            ->limit($limit)
            ->get([
                'items.id',
                'items.name',
                'items.image_url',
                'items.grade',
                'items.market_price',
                'items.npc_sell_price',
                DB::raw('COUNT(loot_entries.id) as drops'),
                DB::raw('SUM(loot_entries.amount) as total_qty'),
            ])
            ->map(function ($row) {
                $price = $row->market_price ?? $row->npc_sell_price;
                $row->estimated_value = $price !== null ? (int) $price * (int) $row->total_qty : null;
                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * Returns a member×day grid: rows are members (active during the
     * period), columns are dates. Cell value is the report count.
     */
    private function memberActivityHeatmap(int $cpId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('loot_reports')
            ->selectRaw('requested_by_id as uid, DATE(created_at) as d, COUNT(*) as c')
            ->where('cp_id', $cpId)
            ->where('status', 'confirmed')
            ->whereNotNull('requested_by_id')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('uid', 'd')
            ->get();

        $userIds = $rows->pluck('uid')->unique()->all();
        if (empty($userIds)) {
            return ['days' => [], 'members' => []];
        }
        $users = User::whereIn('id', $userIds)->pluck('name', 'id');

        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $members = [];
        foreach ($userIds as $uid) {
            $cells = [];
            foreach ($days as $day) {
                $match = $rows->first(fn ($r) => (int) $r->uid === (int) $uid && $r->d === $day);
                $cells[] = (int) ($match->c ?? 0);
            }
            $members[] = [
                'user_id' => (int) $uid,
                'name' => $users[$uid] ?? '?',
                'cells' => $cells,
                'total' => array_sum($cells),
            ];
        }
        usort($members, fn ($a, $b) => $b['total'] <=> $a['total']);

        return ['days' => $days, 'members' => $members];
    }

    private function gradeDistribution(int $cpId): array
    {
        // Reuse the existing warehouse aggregation pattern (incoming -
        // outgoing) per item, then bucket by grade. Items that net to
        // 0 are skipped.
        $incoming = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id', 'items.grade')
            ->get(['items.id', 'items.grade', DB::raw('SUM(loot_entries.amount) as qty')]);

        $outgoing = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id')
            ->pluck(DB::raw('SUM(loot_entries.amount) as qty'), 'items.id');

        $buckets = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'NG' => 0];
        foreach ($incoming as $row) {
            $stock = max(0, (int) $row->qty - (int) ($outgoing[$row->id] ?? 0));
            if ($stock <= 0) {
                continue;
            }
            $grade = $row->grade ?: 'NG';
            $buckets[$grade] = ($buckets[$grade] ?? 0) + $stock;
        }

        return $buckets;
    }

    private function trackerTop(int $cpId, Carbon $from, Carbon $to, int $limit): array
    {
        return DB::table('tracker_contributions')
            ->join('users', 'users.id', '=', 'tracker_contributions.user_id')
            ->where('tracker_contributions.cp_id', $cpId)
            ->whereBetween('tracker_contributions.created_at', [$from, $to])
            ->groupBy('tracker_contributions.user_id', 'users.name')
            ->orderByDesc(DB::raw('SUM(tracker_contributions.points)'))
            ->limit($limit)
            ->get([
                'tracker_contributions.user_id',
                'users.name',
                DB::raw('SUM(tracker_contributions.points) as total_points'),
                DB::raw('COUNT(tracker_contributions.id) as entries'),
            ])
            ->all();
    }

    private function financialScoreboard(int $cpId): array
    {
        $gainedRaw = DB::table('points_logs')
            ->selectRaw('user_id, SUM(adena) as s')
            ->where('cp_id', $cpId)
            ->where('action_type', 'ADENA_GAIN')
            ->groupBy('user_id')
            ->pluck('s', 'user_id');
        $paidRaw = DB::table('points_logs')
            ->selectRaw('user_id, SUM(adena) as s')
            ->where('cp_id', $cpId)
            ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
            ->groupBy('user_id')
            ->pluck('s', 'user_id');

        $totalGained = 0;
        $totalPaid = 0;
        foreach ($gainedRaw as $g) {
            $totalGained += (int) $g;
        }
        foreach ($paidRaw as $p) {
            $totalPaid += abs((int) $p);
        }

        // Top owed: per-user gained - paid (clamped to >=0), sorted desc.
        $userIds = array_unique(array_merge(array_keys($gainedRaw->toArray()), array_keys($paidRaw->toArray())));
        $userNames = User::whereIn('id', $userIds)->pluck('name', 'id');
        $perUser = [];
        foreach ($userIds as $uid) {
            $g = (int) ($gainedRaw[$uid] ?? 0);
            $p = abs((int) ($paidRaw[$uid] ?? 0));
            $owed = max(0, $g - $p);
            if ($owed <= 0) {
                continue;
            }
            $perUser[] = ['user_id' => (int) $uid, 'name' => $userNames[$uid] ?? '?', 'owed' => $owed];
        }
        usort($perUser, fn ($a, $b) => $b['owed'] <=> $a['owed']);

        return [
            'total_gained' => $totalGained,
            'total_paid' => $totalPaid,
            'total_owed' => max(0, $totalGained - $totalPaid),
            'ratio_paid' => $totalGained > 0 ? round(($totalPaid / $totalGained) * 100, 1) : 0.0,
            'top_owed' => array_slice($perUser, 0, 5),
        ];
    }

    private function vaultValue(int $cpId): int
    {
        // Lightweight version of the warehouse aggregation in PartyController.
        // Computes stock value using `market_price ?? npc_sell_price` for
        // every item with net > 0. Skips 'adena' items.
        $incoming = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id', 'items.market_price', 'items.npc_sell_price')
            ->get(['items.id', 'items.market_price', 'items.npc_sell_price', DB::raw('SUM(loot_entries.amount) as qty')]);

        $outgoing = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id')
            ->pluck(DB::raw('SUM(loot_entries.amount) as qty'), 'items.id');

        $total = 0;
        foreach ($incoming as $row) {
            $stock = max(0, (int) $row->qty - (int) ($outgoing[$row->id] ?? 0));
            $price = $row->market_price ?? $row->npc_sell_price;
            if ($stock <= 0 || $price === null) {
                continue;
            }
            $total += $stock * (int) $price;
        }
        return $total;
    }
}
