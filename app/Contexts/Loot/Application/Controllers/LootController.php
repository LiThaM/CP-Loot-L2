<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Models\Wishlist;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LootController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->cp_id) {
            return Inertia::render('Loot/Index', [
                'has_cp' => false
            ]);
        }

        // Load Pending Sessions (Reports)
        $pendingLoot = LootReport::with(['entries.item', 'requestedBy'])
            ->where('cp_id', $user->cp_id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        // Load History (Recent Confirmed/Rejected Reports)
        $history = LootReport::with(['entries.item', 'requestedBy'])
            ->where('cp_id', $user->cp_id)
            ->whereIn('status', ['confirmed', 'rejected'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $wishlist = Wishlist::with('item')
            ->where('cp_id', $user->cp_id)
            ->get();

        $members = \App\Contexts\Identity\Domain\Models\User::where('cp_id', $user->cp_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        // CP Event Configuration for the Leader
        $eventConfigs = CpEventConfig::where('cp_id', $user->cp_id)->get();

        return Inertia::render('Loot/Index', [
            'has_cp' => true,
            'pendingLoot' => $pendingLoot,
            'history' => $history,
            'wishlist' => $wishlist,
            'members' => $members,
            'eventConfigs' => $eventConfigs,
            'isLeader' => $user->cp->leader_id === $user->id
        ]);
    }
}
