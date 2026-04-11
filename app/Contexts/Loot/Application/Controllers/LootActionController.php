<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Services\LootDistributionService;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'integer|exists:users,id',
            'adena_distribution' => 'nullable|in:attendees,cp',
        ]);

        $user = $request->user();

        if (! $user->cp_id) {
            return back()->withErrors(['cp_id' => 'No perteneces a ninguna CP.']);
        }

        DB::transaction(function () use ($request, $user) {
            $recipientIds = null;
            if (is_array($request->recipient_ids) && count($request->recipient_ids) > 0) {
                $recipientIds = User::where('cp_id', $user->cp_id)
                    ->where('membership_status', '!=', 'banned')
                    ->whereIn('id', $request->recipient_ids)
                    ->pluck('id')
                    ->all();
            }

            $report = LootReport::create([
                'cp_id' => $user->cp_id,
                'requested_by_id' => $user->id,
                'event_type' => $request->event_type,
                'status' => 'pending',
                'image_proof' => null,
                'recipient_ids' => $recipientIds,
                'adena_distribution' => $request->input('adena_distribution'),
            ]);

            if ($request->hasFile('image_proof')) {
                $file = $request->file('image_proof');
                $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
                $imagePath = $file->storeAs("loot/{$user->cp_id}", "{$report->id}.{$ext}", 'public');
                $report->image_proof = $imagePath;
                $report->save();
            }

            foreach ($request->items as $itemData) {
                LootEntry::create([
                    'loot_report_id' => $report->id,
                    'item_id' => $itemData['item_id'],
                    'amount' => $itemData['amount'],
                ]);
            }
        });

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
        if (! $isAdmin) {
            if (! $isLeader || $user->cp_id !== $report->cp_id) {
                abort(403, 'No tienes permiso para resolver este reporte de loot.');
            }
        }

        $request->validate([
            'status' => 'required|in:confirmed,rejected',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'exists:users,id',
            'points_per_member' => 'nullable|integer|min:0',
            'event_type' => 'nullable|string|in:FARM,BOSS,EPIC,SIEGE',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.amount' => 'required_with:items|integer|min:1',
            'adena_distribution' => 'nullable|in:attendees,cp',
        ]);

        if ($report->event_type === 'RETURN') {
            $report->update(['status' => $request->status]);

            return back()->with('success', $request->status === 'confirmed' ? 'Devolución aceptada.' : 'Devolución rechazada.');
        }

        if ($request->status === 'rejected') {
            // En lugar de borrar, marcamos como rechazado para mantener la auditoría
            $report->update(['status' => 'rejected']);

            return back()->with('success', 'El reporte ha sido marcado como RECHAZADO.');
        }

        // Logic for "Confirmed" (Success)
        $points = $request->points_per_member ?? 0;

        if ($request->filled('event_type') && $request->event_type !== $report->event_type) {
            $report->update(['event_type' => $request->event_type]);
        }
        if ($request->filled('adena_distribution')) {
            $report->update(['adena_distribution' => $request->adena_distribution]);
        }

        if (is_array($request->items) && count($request->items) > 0) {
            LootEntry::where('loot_report_id', $report->id)->delete();
            foreach ($request->items as $itemData) {
                LootEntry::create([
                    'loot_report_id' => $report->id,
                    'item_id' => $itemData['item_id'],
                    'amount' => $itemData['amount'],
                ]);
            }
        }

        // Use custom CP points config if points were not manually provided
        if (! $request->has('points_per_member')) {
            $config = CpEventConfig::where('cp_id', $report->cp_id)
                ->where('event_type', $report->event_type)
                ->first();
            $points = $config ? $config->points : 0;
        }

        $attendees = $request->recipient_ids ?? [];

        $this->distributionService->distribute($report, $attendees, $points);

        // Adena distribution
        if ($report->status !== 'rejected') {
            $entries = LootEntry::where('loot_report_id', $report->id)->with('item')->get();
            $adenaAmount = $entries->filter(fn ($e) => strtolower($e->item->name) === 'adena')->sum('amount');
            if ($adenaAmount > 0 && $report->adena_distribution === 'attendees' && count($attendees) > 0) {
                $split = intdiv($adenaAmount, count($attendees));
                if ($split > 0) {
                    foreach ($attendees as $uid) {
                        PointsLog::create([
                            'cp_id' => $report->cp_id,
                            'user_id' => $uid,
                            'action_type' => 'ADENA_GAIN',
                            'points' => 0,
                            'adena' => $split,
                            'description' => 'Distribución de Adena del reporte #'.$report->id,
                        ]);
                    }
                }
                // El remanente (adenaAmount % count) se mantiene en el warehouse de la CP.
            }
        }

        return back()->with('success', 'Sesión resuelta. Puntos otorgados a los asistentes.');
    }
}
