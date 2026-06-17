<?php

namespace App\Http\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\CpRequest;
use App\Contexts\Party\Domain\Models\PointsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $role = $user->role->name;

        $stats = [
            'total_cps' => 0,
            'total_members' => 0,
            'total_reports' => 0,
            'total_items' => 0,
            'total_points_global' => 0,
        ];

        $cps = [];
        $chartData = null;
        $members = [];
        $cpInsights = null;
        $cpRequests = [];
        $supportTickets = [];

        if ($role === 'admin') {
            $stats['total_cps'] = ConstParty::where('is_active', true)->count();
            $stats['total_cps_all'] = ConstParty::count();
            $stats['total_members'] = User::whereNotNull('cp_id')->where('membership_status', '!=', 'banned')->count();
            $stats['total_users'] = User::count();
            $stats['total_reports'] = LootReport::where('status', 'confirmed')->count();
            $stats['total_items'] = Item::count();
            $stats['total_adena_distributed'] = (int) PointsLog::where('action_type', 'ADENA_GAIN')->sum('adena');
            $stats['total_points_global'] = (int) PointsLog::sum('points');

            $stats['active_users_24h'] = \App\Contexts\System\Domain\Models\UserActivity::where('created_at', '>=', now()->subDay())
                ->distinct('user_id')
                ->count('user_id');

            $stats['active_users_1h'] = \App\Contexts\System\Domain\Models\UserActivity::where('created_at', '>=', now()->subHour())
                ->distinct('user_id')
                ->count('user_id');

            $stats['total_visits_24h'] = \App\Contexts\System\Domain\Models\UserActivity::where('created_at', '>=', now()->subDay())->count();

            // Delta vs the previous 24h window for ▲/▼ trend chips. Done as
            // straight whereBetween rather than a fancier rollup so the
            // delta query plan is identical to the "current" one (same
            // index scan, just a different time bucket).
            $stats['total_visits_24h_prev'] = \App\Contexts\System\Domain\Models\UserActivity::query()
                ->whereBetween('created_at', [now()->subDays(2), now()->subDay()])
                ->count();
            $stats['active_users_24h_prev'] = \App\Contexts\System\Domain\Models\UserActivity::query()
                ->whereBetween('created_at', [now()->subDays(2), now()->subDay()])
                ->distinct('user_id')
                ->count('user_id');
            $stats['total_cps_prev_week'] = ConstParty::query()
                ->where('is_active', true)
                ->where('created_at', '<=', now()->subWeek())
                ->count();

            $cps = ConstParty::with('leader')->withCount('members')->orderBy('name')->get();

            // Refined chart data: Visits vs Reports (last 14 days)
            $days = collect(range(13, 0))->map(fn ($day) => now()->subDays($day)->format('Y-m-d'));

            $visitActivity = \App\Contexts\System\Domain\Models\UserActivity::where('created_at', '>=', now()->subDays(14))
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $reportActivity = LootReport::where('created_at', '>=', now()->subDays(14))
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            // Sparklines: tiny 14-point arrays that ride along inside the
            // big KPI cards. Visits / DAU recycle the same aggregates the
            // chart above already computes; for CPs we run a one-shot
            // GROUP BY on created_at to track the active-CP curve.
            $dauActivity = \App\Contexts\System\Domain\Models\UserActivity::query()
                ->where('created_at', '>=', now()->subDays(14))
                ->selectRaw('DATE(created_at) as date, COUNT(DISTINCT user_id) as count')
                ->groupBy('date')
                ->pluck('count', 'date');
            $cpsActivity = ConstParty::query()
                ->where('is_active', true)
                ->where('created_at', '>=', now()->subDays(14))
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $stats['visits_sparkline'] = $days->map(fn ($d) => (int) $visitActivity->get($d, 0))->values()->all();
            $stats['dau_sparkline'] = $days->map(fn ($d) => (int) $dauActivity->get($d, 0))->values()->all();
            $stats['cps_sparkline'] = $days->map(fn ($d) => (int) $cpsActivity->get($d, 0))->values()->all();

            $chartData = [
                'labels' => $days->map(fn ($d) => date('d M', strtotime($d))),
                'datasets' => [
                    [
                        'label' => 'Visitas Totales',
                        'data' => $days->map(fn ($d) => $visitActivity->get($d, 0)),
                        'borderColor' => '#8b5cf6',
                        'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Drops Reportados',
                        'data' => $days->map(fn ($d) => $reportActivity->get($d, 0)),
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                ],
            ];

            $supportTickets = \App\Models\SupportTicket::query()
                ->where('status', 'open')
                ->orderByDesc('created_at')
                ->get();

            $cpRequests = CpRequest::query()
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get([
                    'id',
                    'cp_name',
                    'server',
                    'chronicle',
                    'leader_name',
                    'contact_email',
                    'message',
                    'status',
                    'created_at',
                ]);
        } else {
            // Member/Leader quick stats
            $stats['total_members'] = User::where('cp_id', $user->cp_id)
                ->where('membership_status', '!=', 'banned')
                ->count();


            $stats['total_reports'] = LootReport::where('cp_id', $user->cp_id)->count();
            $stats['pending_reports'] = LootReport::where('cp_id', $user->cp_id)
                ->where('status', 'pending')
                ->count();

            $stats['total_points_cp'] = PointsLog::where('cp_id', $user->cp_id)->sum('points');
            $incomingNonAdena = LootEntry::query()
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
                ->whereNull('loot_reports.voided_at')
                ->where('loot_reports.event_type', '!=', 'ASSIGN')
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->sum('loot_entries.amount');

            $outgoingNonAdena = LootEntry::query()
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
                ->whereNull('loot_reports.voided_at')
                ->where('loot_reports.event_type', '=', 'ASSIGN')
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->sum('loot_entries.amount');

            $stats['total_items_cp'] = max(0, (int) $incomingNonAdena - (int) $outgoingNonAdena);

            $adenaIn = LootEntry::query()
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
                ->whereNull('loot_reports.voided_at')
                ->whereNotIn('loot_reports.event_type', ['ADENA_PAYOUT', 'ADENA_GRANT'])
                ->whereRaw('LOWER(items.name) = ?', ['adena'])
                ->sum('loot_entries.amount');

            $adenaPaidSum = PointsLog::where('cp_id', $user->cp_id)
                ->where('action_type', 'ADENA_PAYOUT')
                ->sum('adena');

            $stats['warehouse_adena'] = max(0, (int) $adenaIn + (int) $adenaPaidSum);

            // Personal stats for the leader/member
            $stats['personal_points'] = $user->total_points;
            $personalAssigned = LootEntry::query()
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
                ->whereNull('loot_reports.voided_at')
                ->where('loot_reports.event_type', 'ASSIGN')
                ->where('loot_entries.awarded_to', $user->id)
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->sum('loot_entries.amount');

            $personalReturned = LootEntry::query()
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
                ->whereNull('loot_reports.voided_at')
                ->where('loot_reports.event_type', 'RETURN')
                ->where('loot_entries.awarded_to', $user->id)
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->sum('loot_entries.amount');

            $stats['personal_items'] = max(0, (int) $personalAssigned - (int) $personalReturned);

            $personalAdenaGained = (int) PointsLog::where('cp_id', $user->cp_id)
                ->where('user_id', $user->id)
                ->where('action_type', 'ADENA_GAIN')
                ->sum('adena');
            $personalAdenaPaid = abs((int) PointsLog::where('cp_id', $user->cp_id)
                ->where('user_id', $user->id)
                ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
                ->sum('adena'));
            $stats['personal_adena_gained'] = $personalAdenaGained;
            $stats['personal_adena_paid'] = $personalAdenaPaid;
            $stats['personal_adena_owed'] = max(0, $personalAdenaGained - $personalAdenaPaid);

            $stats['personal_latest_items'] = LootEntry::query()
                ->select([
                    'loot_reports.id as report_id',
                    'loot_reports.created_at',
                    'items.name',
                    'items.grade',
                    'items.image_url',
                    'loot_entries.amount',
                ])
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
                ->whereNull('loot_reports.voided_at')
                ->where('loot_reports.event_type', 'ASSIGN')
                ->where('loot_entries.awarded_to', $user->id)
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->orderByDesc('loot_reports.created_at')
                ->limit(8)
                ->get();

            // Chart data for CP activity (last 7 days)
            $days = collect(range(6, 0))->map(fn ($day) => now()->subDays($day)->format('Y-m-d'));
            $cpActivity = LootReport::where('cp_id', $user->cp_id)
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $chartData = [
                'labels' => $days->map(fn ($d) => date('D', strtotime($d))),
                'datasets' => [
                    [
                        'label' => 'Actividad de CP',
                        'data' => $days->map(fn ($d) => $cpActivity->get($d, 0)),
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.25)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                ],
            ];

            $members = User::query()
                ->where('cp_id', $user->cp_id)
                ->select(['id', 'name', 'role_id', 'cp_id', 'membership_status'])
                ->with(['role:id,name'])
                ->orderBy('name')
                ->get();

            $cpAdenaGained = (int) PointsLog::where('cp_id', $user->cp_id)
                ->where('action_type', 'ADENA_GAIN')
                ->sum('adena');
            $cpAdenaPaid = abs((int) PointsLog::where('cp_id', $user->cp_id)
                ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
                ->sum('adena'));
            $cpAdenaOwed = max(0, $cpAdenaGained - $cpAdenaPaid);

            $stats['warehouse_adena_net'] = $stats['warehouse_adena'] - $cpAdenaOwed;

            $since = now()->subDays(7);

            $topPointsWeek = PointsLog::query()
                ->select([
                    'users.id',
                    'users.name',
                    DB::raw('SUM(points_logs.points) as points'),
                    DB::raw('COUNT(points_logs.id) as sessions'),
                ])
                ->join('users', 'users.id', '=', 'points_logs.user_id')
                ->where('points_logs.cp_id', $user->cp_id)
                ->where('points_logs.created_at', '>=', $since)
                ->where('points_logs.points', '>', 0)
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('points')
                ->limit(5)
                ->get();

            $topActivityWeek = PointsLog::query()
                ->select([
                    'users.id',
                    'users.name',
                    DB::raw('COUNT(points_logs.id) as sessions'),
                    DB::raw('SUM(points_logs.points) as points'),
                ])
                ->join('users', 'users.id', '=', 'points_logs.user_id')
                ->where('points_logs.cp_id', $user->cp_id)
                ->where('points_logs.created_at', '>=', $since)
                ->where('points_logs.points', '>', 0)
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('sessions')
                ->orderByDesc('points')
                ->limit(5)
                ->get();

            $topAdenaWeek = PointsLog::query()
                ->select([
                    'users.id',
                    'users.name',
                    DB::raw('SUM(points_logs.adena) as adena'),
                    DB::raw('COUNT(points_logs.id) as sessions'),
                ])
                ->join('users', 'users.id', '=', 'points_logs.user_id')
                ->where('points_logs.cp_id', $user->cp_id)
                ->where('points_logs.created_at', '>=', $since)
                ->where('points_logs.action_type', 'ADENA_GAIN')
                ->where('points_logs.adena', '>', 0)
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('adena')
                ->limit(5)
                ->get();

            $memberIds = $members->pluck('id')->all();
            $adenaGainedByUser = PointsLog::query()
                ->selectRaw('user_id, SUM(adena) as total')
                ->where('cp_id', $user->cp_id)
                ->where('action_type', 'ADENA_GAIN')
                ->whereIn('user_id', $memberIds)
                ->groupBy('user_id')
                ->pluck('total', 'user_id');
            $adenaPaidByUser = PointsLog::query()
                ->selectRaw('user_id, SUM(adena) as total')
                ->where('cp_id', $user->cp_id)
                ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
                ->whereIn('user_id', $memberIds)
                ->groupBy('user_id')
                ->pluck('total', 'user_id');

            $topAdenaOwed = $members->map(function ($m) use ($adenaGainedByUser, $adenaPaidByUser) {
                $g = (int) ($adenaGainedByUser[$m->id] ?? 0);
                $p = abs((int) ($adenaPaidByUser[$m->id] ?? 0));

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'owed' => max(0, $g - $p),
                ];
            })->filter(fn ($row) => (int) $row['owed'] > 0)
                ->sortByDesc('owed')
                ->values()
                ->take(5)
                ->values();

            $latestItems = LootEntry::query()
                ->select([
                    'loot_reports.id as report_id',
                    'loot_reports.created_at',
                    'loot_reports.event_type',
                    'items.name',
                    'items.grade',
                    'items.image_url',
                    'loot_entries.amount',
                ])
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
                ->whereNull('loot_reports.voided_at')
                ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'ADENA_PAYOUT', 'ADENA_GRANT'])
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->orderByDesc('loot_reports.created_at')
                ->limit(8)
                ->get();

            // Donations now live as DONATION loot reports (reviewable in /loot).
            // Non-tracker CPs rank donors by donated value (last 7 days): adena
            // entries count raw, item entries at market (NPC fallback) price.
            $donationValueExpr = "SUM(CASE WHEN LOWER(items.name) = 'adena' THEN loot_entries.amount ELSE loot_entries.amount * COALESCE(items.market_price, items.npc_sell_price, 0) END)";
            $topDonationsWeek = DB::table('loot_reports')
                ->join('loot_entries', 'loot_entries.loot_report_id', '=', 'loot_reports.id')
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('users', 'users.id', '=', 'loot_reports.requested_by_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.event_type', 'DONATION')
                ->where('loot_reports.status', 'confirmed')
                ->whereNull('loot_reports.voided_at')
                ->where('loot_reports.created_at', '>=', $since)
                ->groupBy('users.id', 'users.name')
                ->orderByDesc(DB::raw($donationValueExpr))
                ->limit(5)
                ->get([
                    'users.id',
                    'users.name',
                    DB::raw($donationValueExpr.' as donated'),
                    DB::raw('COUNT(DISTINCT loot_reports.id) as donations'),
                ]);

            // Tracker (DKP) standings — only for CPs running the value tracker.
            $cpModel = ConstParty::find($user->cp_id);
            $trackerEnabled = (bool) optional($cpModel)->tracker_enabled;
            $trackerRanking = [];
            if ($trackerEnabled) {
                $trackerRanking = DB::table('tracker_contributions')
                    ->join('users', 'users.id', '=', 'tracker_contributions.user_id')
                    ->where('tracker_contributions.cp_id', $user->cp_id)
                    ->groupBy('tracker_contributions.user_id', 'users.name')
                    ->orderByDesc(DB::raw('SUM(tracker_contributions.points)'))
                    ->limit(5)
                    ->get([
                        'tracker_contributions.user_id as id',
                        'users.name',
                        DB::raw('SUM(tracker_contributions.points) as points'),
                        DB::raw('COUNT(tracker_contributions.id) as entries'),
                    ]);
            }

            // Weekly objectives (items the CP hunts) with computed progress.
            $trackerService = app(\App\Contexts\Party\Application\Services\TrackerContributionService::class);
            $weeklyObjectives = \App\Contexts\Party\Domain\Models\CpWeeklyObjective::with('item:id,name,grade,image_url')
                ->where('cp_id', $user->cp_id)
                ->orderBy('completed_at') // active (null) first
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($o) use ($trackerService) {
                    $progress = $trackerService->objectiveProgress($o->cp_id, $o->item_id, $o->created_at);

                    return [
                        'id' => $o->id,
                        'item' => $o->item ? [
                            'id' => $o->item->id,
                            'name' => $o->item->name,
                            'grade' => $o->item->grade,
                            'image_url' => $o->item->image_url,
                        ] : null,
                        'target_quantity' => (int) $o->target_quantity,
                        'multiplier' => (float) $o->multiplier,
                        'progress' => $progress,
                        'completed' => $o->completed_at !== null || $progress >= (int) $o->target_quantity,
                    ];
                })
                ->values();

            $cpInsights = [
                'cpAdenaOwed' => $cpAdenaOwed,
                'cpAdenaPaid' => $cpAdenaPaid,
                'cpAdenaGained' => $cpAdenaGained,
                'topPointsWeek' => $topPointsWeek,
                'topActivityWeek' => $topActivityWeek,
                'topAdenaWeek' => $topAdenaWeek,
                'topAdenaOwed' => $topAdenaOwed,
                'topDonationsWeek' => $topDonationsWeek,
                'trackerRanking' => $trackerRanking,
                'trackerEnabled' => $trackerEnabled,
                'weeklyObjectives' => $weeklyObjectives,
                'canManageObjectives' => in_array($role, ['admin', 'cp_leader', 'accountant'], true),
                'latestItems' => $latestItems,
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'cps' => $cps,
            'chartData' => $chartData,
            'members' => $members,
            'cpInsights' => $cpInsights,
            'cpRequests' => $cpRequests,
            'supportTickets' => $supportTickets,
        ]);
    }
}
