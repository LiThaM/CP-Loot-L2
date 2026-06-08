<?php

namespace App\Http\Controllers;

use App\Contexts\Party\Domain\Models\TrackerContribution;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal analytics page for the authenticated user. Mirrors the
 * structure of `PartyStatsController` but every aggregation is scoped
 * to `user_id = me` instead of `cp_id = mine`. Reuses the same period
 * options (7/30/90 days, default 30).
 *
 * Read-only. No data mutation. Lives in `Http/Controllers/` next to
 * `ProfileController` (the existing settings page).
 */
class ProfileStatsController extends Controller
{
    private const ALLOWED_PERIODS = [7, 30, 90];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->cp_id, 403);

        $cp = $user->cp;
        abort_unless($cp, 403);

        $period = (int) $request->query('period', 30);
        if (! in_array($period, self::ALLOWED_PERIODS, true)) {
            $period = 30;
        }

        $now = now();
        $from = $now->copy()->subDays($period - 1)->startOfDay();

        return Inertia::render('Profile/Stats', [
            'me' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role?->name,
                'cp' => [
                    'id' => $cp->id,
                    'name' => $cp->name,
                    'chronicle' => $cp->chronicle,
                    'tracker_enabled' => (bool) $cp->tracker_enabled,
                ],
            ],
            'period' => $period,
            'periodOptions' => self::ALLOWED_PERIODS,
            'kpis' => $this->kpis($user->id, $cp->id, $from, $now),
            'pointsTimeline' => $this->pointsTimeline($user->id, $cp->id, $from, $now),
            'adenaTimeline' => $this->adenaTimeline($user->id, $cp->id, $from, $now),
            'topItemsReceived' => $this->topItemsReceived($user->id, $cp->id, $from, $now, 10),
            'myRank' => $this->myRank($user->id, $cp->id),
            'myTracker' => $cp->tracker_enabled ? $this->myTracker($user->id, $cp->id) : null,
            'activityCalendar' => $this->activityCalendar($user->id, $cp->id, $from, $now),
            'characters' => $user->characters()->with('mainClass:id,name,race')->get([
                'id', 'name', 'l2_class_id', 'race', 'level',
            ]),
        ]);
    }

    private function kpis(int $userId, int $cpId, Carbon $from, Carbon $to): array
    {
        $totalPoints = (int) DB::table('points_logs')
            ->where('user_id', $userId)
            ->where('cp_id', $cpId)
            ->sum('points');

        $adenaGained = (int) DB::table('points_logs')
            ->where('user_id', $userId)
            ->where('cp_id', $cpId)
            ->where('action_type', 'ADENA_GAIN')
            ->whereBetween('created_at', [$from, $to])
            ->sum('adena');

        $adenaGainedLifetime = (int) DB::table('points_logs')
            ->where('user_id', $userId)
            ->where('cp_id', $cpId)
            ->where('action_type', 'ADENA_GAIN')
            ->sum('adena');
        $adenaPaidLifetime = abs((int) DB::table('points_logs')
            ->where('user_id', $userId)
            ->where('cp_id', $cpId)
            ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
            ->sum('adena'));
        $adenaOwed = max(0, $adenaGainedLifetime - $adenaPaidLifetime);

        $reportsSubmitted = DB::table('loot_reports')
            ->where('requested_by_id', $userId)
            ->where('cp_id', $cpId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $characters = DB::table('characters')->where('user_id', $userId)->count();

        return [
            'total_points' => $totalPoints,
            'adena_gained_period' => $adenaGained,
            'adena_owed' => $adenaOwed,
            'reports_submitted' => $reportsSubmitted,
            'characters_count' => $characters,
        ];
    }

    private function pointsTimeline(int $userId, int $cpId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('points_logs')
            ->selectRaw('DATE(created_at) as d, SUM(points) as s')
            ->where('user_id', $userId)
            ->where('cp_id', $cpId)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('d')
            ->get();

        return $this->fillDailyTimeline($from, $to, $rows, 'd', fn ($r) => (int) $r->s);
    }

    private function adenaTimeline(int $userId, int $cpId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('points_logs')
            ->selectRaw('DATE(created_at) as d, action_type, SUM(adena) as s')
            ->where('user_id', $userId)
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

    private function topItemsReceived(int $userId, int $cpId, Carbon $from, Carbon $to, int $limit): array
    {
        return DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_entries.awarded_to', $userId)
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereBetween('loot_reports.created_at', [$from, $to])
            ->groupBy('items.id', 'items.name', 'items.image_url', 'items.grade', 'items.market_price', 'items.npc_sell_price')
            ->orderByDesc(DB::raw('SUM(loot_entries.amount)'))
            ->limit($limit)
            ->get([
                'items.id',
                'items.name',
                'items.image_url',
                'items.grade',
                'items.market_price',
                'items.npc_sell_price',
                DB::raw('COUNT(loot_entries.id) as awards'),
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
     * Position of the user in the CP's all-time points leaderboard,
     * plus their points and the total number of ranked members.
     */
    private function myRank(int $userId, int $cpId): array
    {
        $ranking = DB::table('points_logs')
            ->selectRaw('user_id, SUM(points) as total')
            ->where('cp_id', $cpId)
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->get();

        $position = null;
        $myPoints = 0;
        foreach ($ranking as $idx => $row) {
            if ((int) $row->user_id === $userId) {
                $position = $idx + 1;
                $myPoints = (int) $row->total;
                break;
            }
        }

        return [
            'position' => $position,
            'total_members' => $ranking->count(),
            'points' => $myPoints,
        ];
    }

    private function myTracker(int $userId, int $cpId): array
    {
        $ranking = DB::table('tracker_contributions')
            ->selectRaw('user_id, SUM(points) as total, COUNT(*) as entries')
            ->where('cp_id', $cpId)
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->get();

        $position = null;
        $myPoints = 0.0;
        $myEntries = 0;
        foreach ($ranking as $idx => $row) {
            if ((int) $row->user_id === $userId) {
                $position = $idx + 1;
                $myPoints = (float) $row->total;
                $myEntries = (int) $row->entries;
                break;
            }
        }

        return [
            'position' => $position,
            'total_contributors' => $ranking->count(),
            'points' => $myPoints,
            'entries' => $myEntries,
        ];
    }

    private function activityCalendar(int $userId, int $cpId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('loot_reports')
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('requested_by_id', $userId)
            ->where('cp_id', $cpId)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('d')
            ->get();

        $cells = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $label = $cursor->toDateString();
            $match = $rows->first(fn ($r) => $r->d === $label);
            $cells[] = ['date' => $label, 'count' => (int) ($match->c ?? 0)];
            $cursor->addDay();
        }
        return $cells;
    }

    /**
     * Helper: fills a date range with zeros where the group-by produced
     * no row, ensuring the chart has a continuous x-axis.
     */
    private function fillDailyTimeline(Carbon $from, Carbon $to, $rows, string $dateField, callable $valueExtractor): array
    {
        $labels = [];
        $values = [];

        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $label = $cursor->toDateString();
            $labels[] = $label;
            $match = $rows->first(fn ($r) => $r->{$dateField} === $label);
            $values[] = $match ? $valueExtractor($match) : 0;
            $cursor->addDay();
        }
        return ['labels' => $labels, 'values' => $values];
    }
}
