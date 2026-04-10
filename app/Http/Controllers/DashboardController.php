<?php

namespace App\Http\Controllers;

use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Loot\Domain\Models\Item;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

        if ($role === 'admin') {
            $stats['total_cps'] = ConstParty::count();
            $stats['total_members'] = \App\Contexts\Identity\Domain\Models\User::count();
            $stats['total_reports'] = \App\Contexts\Loot\Domain\Models\LootReport::where('status', 'confirmed')->count();
            $stats['total_items'] = Item::count();
            $stats['total_points_global'] = \App\Contexts\Party\Domain\Models\PointsLog::sum('points');

            $cps = ConstParty::with('leader')->withCount('members')->orderBy('name')->get();

            // Simple chart data: Reports created in the last 7 days
            $days = collect(range(6, 0))->map(fn($day) => now()->subDays($day)->format('Y-m-d'));
            $reportActivity = \App\Contexts\Loot\Domain\Models\LootReport::where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $chartData = [
                'labels' => $days->map(fn($d) => date('D', strtotime($d))),
                'datasets' => [
                    [
                        'label' => 'Drops Reportados',
                        'data' => $days->map(fn($d) => $reportActivity->get($d, 0)),
                        'borderColor' => '#ef4444',
                        'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    ]
                ]
            ];
        } else {
            // Member/Leader quick stats
            $stats['total_members'] = \App\Contexts\Identity\Domain\Models\User::where('cp_id', $user->cp_id)
                ->whereHas('role', fn($q) => $q->where('name', '!=', 'admin'))
                ->count();
            
            $stats['total_reports'] = \App\Contexts\Loot\Domain\Models\LootReport::where('cp_id', $user->cp_id)->count();
            $stats['pending_reports'] = \App\Contexts\Loot\Domain\Models\LootReport::where('cp_id', $user->cp_id)
                ->where('status', 'pending')
                ->count();
            
            $stats['total_points_cp'] = \App\Contexts\Party\Domain\Models\PointsLog::where('cp_id', $user->cp_id)->sum('points');
            $stats['total_items_cp'] = \App\Contexts\Loot\Domain\Models\LootEntry::whereHas('report', fn($q) => $q->where('cp_id', $user->cp_id))
                ->sum('amount');

            // Personal stats for the leader/member
            $stats['personal_points'] = $user->total_points;
            $stats['personal_items'] = \App\Contexts\Loot\Domain\Models\LootEntry::where('awarded_to', $user->id)->sum('amount');

            // Chart data for CP activity (last 7 days)
            $days = collect(range(6, 0))->map(fn($day) => now()->subDays($day)->format('Y-m-d'));
            $cpActivity = \App\Contexts\Loot\Domain\Models\LootReport::where('cp_id', $user->cp_id)
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $chartData = [
                'labels' => $days->map(fn($d) => date('D', strtotime($d))),
                'datasets' => [
                    [
                        'label' => 'Actividad de CP',
                        'data' => $days->map(fn($d) => $cpActivity->get($d, 0)),
                        'borderColor' => '#ef4444',
                        'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                        'fill' => true,
                        'tension' => 0.4
                    ]
                ]
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'cps' => $cps,
            'chartData' => $chartData
        ]);
    }
}
