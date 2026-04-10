<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user->cp_id) {
            return Inertia::render('Party/Index', ['has_cp' => false]);
        }

        $cp = $user->cp->load('leader');
        
        $members = \App\Contexts\Identity\Domain\Models\User::where('cp_id', $user->cp_id)
            ->whereHas('role', function($query) {
                $query->where('name', '!=', 'admin');
            })
            ->withSum('pointsLogs as total_points', 'points')
            ->orderByDesc('total_points')
            ->get();

        $eventConfigs = \App\Contexts\Loot\Domain\Models\CpEventConfig::where('cp_id', $user->cp_id)->get();

        return Inertia::render('Party/Index', [
            'has_cp' => true,
            'cp' => $cp,
            'members' => $members,
            'eventConfigs' => $eventConfigs,
            'isLeader' => $user->id === $cp->leader_id
        ]);
    }
}
