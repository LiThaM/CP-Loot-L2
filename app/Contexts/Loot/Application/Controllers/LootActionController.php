<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Services\LootDistributionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LootActionController extends Controller
{
    public function __construct(
        protected LootDistributionService $distributionService
    ) {}

    /**
     * Store a multi-item loot report (Session).
     */
    public function store(Request $request)
    {
        $request->validate([
            'event_type' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.amount' => 'required|integer|min:1',
            'image_proof' => 'nullable|image|max:3072', // 3MB
        ]);

        $user = $request->user();

        if (!$user->cp_id) {
            return back()->withErrors(['cp_id' => 'No perteneces a ninguna CP.']);
        }

        $imagePath = null;
        if ($request->hasFile('image_proof')) {
            $imagePath = $request->file('image_proof')->store('loot', 'public');
        }

        // 1. Create the Report (The Session)
        $report = LootReport::create([
            'cp_id' => $user->cp_id,
            'requested_by_id' => $user->id,
            'event_type' => $request->event_type,
            'status' => 'pending',
            'image_proof' => $imagePath,
        ]);

        // 2. Create individual entries for each item in the session
        foreach ($request->items as $itemData) {
            LootEntry::create([
                'loot_report_id' => $report->id,
                'item_id' => $itemData['item_id'],
                'amount' => $itemData['amount'],
            ]);
        }

        return back()->with('success', 'Sesión de loot reportada. Pendiente de aprobación por el líder.');
    }

    /**
     * Resolve a whole loot session.
     */
    public function resolve(Request $request, LootReport $report)
    {
        $user = $request->user();
        $isAdmin = $user->role->name === 'admin';
        $isLeader = $user->role->name === 'cp_leader';
        $isFounder = $user->cp_id && $user->cp && $user->id === $user->cp->leader_id;

        // Solo el Admin o Líderes (Fundador o Co-Líderes) de la misma CP pueden resolver
        if (!$isAdmin) {
            if (!$isLeader || $user->cp_id !== $report->cp_id) {
                abort(403, 'No tienes permiso para resolver este reporte de loot.');
            }
        }

        $request->validate([
            'status' => 'required|in:confirmed,rejected',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'exists:users,id',
            'points_per_member' => 'nullable|integer|min:0',
        ]);

        if ($request->status === 'rejected') {
            // En lugar de borrar, marcamos como rechazado para mantener la auditoría
            $report->update(['status' => 'rejected']);
            
            return back()->with('success', 'El reporte ha sido marcado como RECHAZADO.');
        }

        // Logic for "Confirmed" (Success)
        $points = $request->points_per_member ?? 0;
        
        // Use custom CP points config if points were not manually provided
        if (!$request->has('points_per_member')) {
            $config = CpEventConfig::where('cp_id', $report->cp_id)
                ->where('event_type', $report->event_type)
                ->first();
            $points = $config ? $config->points : 0;
        }

        $attendees = $request->recipient_ids ?? [];
        
        $this->distributionService->distribute($report, $attendees, $points);

        return back()->with('success', 'Sesión resuelta. Puntos otorgados a los asistentes.');
    }
}
