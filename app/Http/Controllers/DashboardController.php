<?php

namespace App\Http\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Domain\Models\ConstParty;
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

        if ($role === 'admin') {
            $stats['total_cps'] = ConstParty::count();
            $stats['total_members'] = User::where('membership_status', '!=', 'banned')->count();
            $stats['total_reports'] = LootReport::where('status', 'confirmed')->count();
            $stats['total_items'] = Item::count();
            $stats['total_points_global'] = PointsLog::sum('points');

            $cps = ConstParty::with('leader')->withCount('members')->orderBy('name')->get();

            // Simple chart data: Reports created in the last 7 days
            $days = collect(range(6, 0))->map(fn ($day) => now()->subDays($day)->format('Y-m-d'));
            $reportActivity = LootReport::where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $chartData = [
                'labels' => $days->map(fn ($d) => date('D', strtotime($d))),
                'datasets' => [
                    [
                        'label' => 'Drops Reportados',
                        'data' => $days->map(fn ($d) => $reportActivity->get($d, 0)),
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.25)',
                    ],
                ],
            ];
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
                ->where('loot_reports.event_type', '!=', 'ASSIGN')
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->sum('loot_entries.amount');

            $outgoingNonAdena = LootEntry::query()
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
                ->where('loot_reports.event_type', '=', 'ASSIGN')
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->sum('loot_entries.amount');

            $stats['total_items_cp'] = max(0, (int) $incomingNonAdena - (int) $outgoingNonAdena);

            $adenaIn = LootEntry::query()
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
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
                ->where('loot_reports.event_type', 'ASSIGN')
                ->where('loot_entries.awarded_to', $user->id)
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->sum('loot_entries.amount');

            $personalReturned = LootEntry::query()
                ->join('items', 'items.id', '=', 'loot_entries.item_id')
                ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                ->where('loot_reports.cp_id', $user->cp_id)
                ->where('loot_reports.status', 'confirmed')
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
                ->where('action_type', 'ADENA_PAYOUT')
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
                ->where('action_type', 'ADENA_PAYOUT')
                ->sum('adena'));
            $cpAdenaOwed = max(0, $cpAdenaGained - $cpAdenaPaid);

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
                ->where('action_type', 'ADENA_PAYOUT')
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
                ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'ADENA_PAYOUT', 'ADENA_GRANT'])
                ->whereRaw('LOWER(items.name) != ?', ['adena'])
                ->orderByDesc('loot_reports.created_at')
                ->limit(8)
                ->get();

            $cpInsights = [
                'cpAdenaOwed' => $cpAdenaOwed,
                'cpAdenaPaid' => $cpAdenaPaid,
                'cpAdenaGained' => $cpAdenaGained,
                'topPointsWeek' => $topPointsWeek,
                'topActivityWeek' => $topActivityWeek,
                'topAdenaWeek' => $topAdenaWeek,
                'topAdenaOwed' => $topAdenaOwed,
                'latestItems' => $latestItems,
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'cps' => $cps,
            'chartData' => $chartData,
            'members' => $members,
            'cpInsights' => $cpInsights,
        ]);
    }
}
