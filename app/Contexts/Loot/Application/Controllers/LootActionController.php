<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\Loot\Domain\Services\LootDistributionService;
use App\Contexts\Party\Application\Services\TrackerContributionService;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LootActionController extends Controller
{
    public function __construct(
        protected LootDistributionService $distributionService,
        protected TrackerContributionService $trackerService,
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
            // Legacy field kept so old clients keep working.
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'integer|exists:users,id',
            // New shape: attendees can include externals.
            'attendees' => 'nullable|array',
            'attendees.*.user_id' => 'nullable|integer|exists:users,id',
            'attendees.*.character_id' => 'nullable|integer|exists:characters,id',
            'attendees.*.external_name' => 'nullable|string|max:80',
            // Legacy binary distribution + new percentage. Either may arrive.
            'adena_distribution' => 'nullable|in:attendees,cp',
            'cp_share_pct' => 'nullable|integer|min:0|max:100',
        ]);

        $user = $request->user();

        if (! $user->cp_id) {
            return back()->withErrors(['cp_id' => 'No perteneces a ninguna CP.']);
        }

        $normalizedAttendees = $this->normalizeAttendees($request, $user->cp_id);
        if ($normalizedAttendees === null) {
            return back()->withErrors(['attendees' => 'Cada attendee necesita user_id (miembro) o external_name (externo), no ambos.']);
        }

        $cpSharePct = $this->resolveCpSharePct($request);

        DB::transaction(function () use ($request, $user, $normalizedAttendees, $cpSharePct) {
            // Mirror the modern attendees list back into the legacy JSON column
            // so old screens that still read `recipient_ids` keep working until
            // the full transition is done.
            $memberIds = collect($normalizedAttendees)
                ->filter(fn ($a) => !$a['is_external'])
                ->pluck('user_id')
                ->values()
                ->all();

            $report = LootReport::create([
                'cp_id' => $user->cp_id,
                'requested_by_id' => $user->id,
                'event_type' => $request->event_type,
                'status' => 'pending',
                'image_proof' => null,
                'recipient_ids' => !empty($memberIds) ? $memberIds : null,
                'adena_distribution' => $request->input('adena_distribution'),
                'cp_share_pct' => $cpSharePct,
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

            foreach ($normalizedAttendees as $attendee) {
                LootReportAttendee::create([
                    'loot_report_id' => $report->id,
                    'user_id' => $attendee['user_id'],
                    'character_id' => $attendee['character_id'] ?? null,
                    'external_name' => $attendee['external_name'],
                    'is_external' => $attendee['is_external'],
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

        if (! $isAdmin) {
            if (! $isLeader || $user->cp_id !== $report->cp_id) {
                abort(403, 'No tienes permiso para resolver este reporte de loot.');
            }
        }

        $request->validate([
            'status' => 'required|in:confirmed,rejected',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'exists:users,id',
            'attendees' => 'nullable|array',
            'attendees.*.user_id' => 'nullable|integer|exists:users,id',
            'attendees.*.external_name' => 'nullable|string|max:80',
            'points_per_member' => 'nullable|integer|min:0',
            'event_type' => 'nullable|string|in:FARM,BOSS,EPIC,SIEGE',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.amount' => 'required_with:items|integer|min:1',
            'adena_distribution' => 'nullable|in:attendees,cp',
            'cp_share_pct' => 'nullable|integer|min:0|max:100',
        ]);

        if ($report->event_type === 'RETURN') {
            $report->update(['status' => $request->status]);

            return back()->with('success', $request->status === 'confirmed' ? 'Devolución aceptada.' : 'Devolución rechazada.');
        }

        if ($request->status === 'rejected') {
            $report->update(['status' => 'rejected']);

            return back()->with('success', 'El reporte ha sido marcado como RECHAZADO.');
        }

        $points = $request->points_per_member ?? 0;

        if ($request->filled('event_type') && $request->event_type !== $report->event_type) {
            $report->update(['event_type' => $request->event_type]);
        }
        if ($request->filled('adena_distribution')) {
            $report->update(['adena_distribution' => $request->adena_distribution]);
        }
        if ($request->filled('cp_share_pct') || $request->filled('adena_distribution')) {
            $report->update(['cp_share_pct' => $this->resolveCpSharePct($request, $report->cp_share_pct)]);
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

        // If the leader edited the attendee list during resolve, rebuild the
        // attendee rows. We only touch the table when the caller actually
        // sends `attendees` (or legacy `recipient_ids`) — otherwise we keep
        // whatever the original report already had.
        $hasNewAttendees = $request->has('attendees') || $request->has('recipient_ids');
        if ($hasNewAttendees) {
            $normalized = $this->normalizeAttendees($request, $report->cp_id);
            if ($normalized === null) {
                return back()->withErrors(['attendees' => 'Cada attendee necesita user_id o external_name, no ambos.']);
            }
            DB::transaction(function () use ($report, $normalized) {
                LootReportAttendee::where('loot_report_id', $report->id)->delete();
                foreach ($normalized as $attendee) {
                    LootReportAttendee::create([
                        'loot_report_id' => $report->id,
                        'user_id' => $attendee['user_id'],
                        'external_name' => $attendee['external_name'],
                        'is_external' => $attendee['is_external'],
                    ]);
                }
                $memberIds = collect($normalized)
                    ->filter(fn ($a) => !$a['is_external'])
                    ->pluck('user_id')
                    ->values()
                    ->all();
                $report->update(['recipient_ids' => !empty($memberIds) ? $memberIds : null]);
            });
        }

        if (! $request->has('points_per_member')) {
            $config = CpEventConfig::where('cp_id', $report->cp_id)
                ->where('event_type', $report->event_type)
                ->first();
            $points = $config ? $config->points : 0;
        }

        // Distribution service still consumes the JSON ids — pass the member
        // subset of the (possibly refreshed) attendee list.
        $attendeeUserIds = LootReportAttendee::where('loot_report_id', $report->id)
            ->where('is_external', false)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        $this->distributionService->distribute($report, $attendeeUserIds, $points);

        // distribute() only flips the report to "confirmed" when there's at
        // least one internal member to award points to. An all-external session
        // has none, so confirm it explicitly here — otherwise approving it would
        // silently leave the report pending.
        if ($report->fresh()->status !== 'confirmed') {
            $report->update(['status' => 'confirmed']);
        }

        // Best-effort: if this CP has the value-based DKP tracker turned on,
        // derive parallel tracker_contributions from the confirmed entries.
        // Failures here must not roll back the loot confirmation above.
        if ($report->fresh()->status === 'confirmed') {
            $this->trackerService->recordFromReport($report->fresh()->load('cp', 'attendees'));
        }

        // Adena distribution on the FARM report itself (legacy path for
        // sessions that include adena drops directly, not via SELL). The
        // proper mixed split lives in PartyController::sell — here we only
        // honour the simple "all to CP fund" or "split equally among
        // attendees" semantics that the legacy column supported.
        if ($report->status !== 'rejected') {
            $entries = LootEntry::where('loot_report_id', $report->id)->with('item')->get();
            $adenaAmount = $entries->filter(fn ($e) => strtolower($e->item->name) === 'adena')->sum('amount');
            $cpSharePct = $report->fresh()->cp_share_pct ?? 0;
            if ($adenaAmount > 0 && $cpSharePct < 100 && count($attendeeUserIds) > 0) {
                $toAttendees = (int) floor($adenaAmount * (100 - $cpSharePct) / 100);
                $split = intdiv($toAttendees, count($attendeeUserIds));
                if ($split > 0) {
                    foreach ($attendeeUserIds as $uid) {
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
                // El remanente (toAttendees % count + cpShare) se queda en el CP fund.
            }
        }

        return back()->with('success', 'Sesión resuelta. Puntos otorgados a los asistentes.');
    }

    /**
     * Normalise the attendee payload (new `attendees` array OR legacy
     * `recipient_ids` ints) into `[{user_id, external_name, is_external}]`.
     * Returns null when validation rules (one-of: user_id|external_name)
     * are violated.
     *
     * @return array<int,array{user_id:?int,external_name:?string,is_external:bool}>|null
     */
    private function normalizeAttendees(Request $request, int $cpId): ?array
    {
        // The resolve modal sends externals in `attendees` and internal members
        // in `recipient_ids` (legacy). Honour BOTH by combining them: otherwise
        // picking any external made the attendees array non-empty and the
        // internal members in recipient_ids were silently dropped, leaving the
        // report unconfirmed.
        $inputs = [];
        if (is_array($request->attendees)) {
            foreach ($request->attendees as $att) {
                if (is_array($att)) {
                    $inputs[] = $att;
                }
            }
        }
        if (is_array($request->recipient_ids)) {
            foreach ($request->recipient_ids as $uid) {
                if ($uid !== null && $uid !== '') {
                    $inputs[] = ['user_id' => (int) $uid];
                }
            }
        }

        if (empty($inputs)) {
            return [];
        }

        $rows = [];
        $seenUserIds = [];
        $userIdsToCheck = [];
        foreach ($inputs as $att) {
            $userId = isset($att['user_id']) && $att['user_id'] !== '' ? (int) $att['user_id'] : null;
            $charId = isset($att['character_id']) && $att['character_id'] !== '' ? (int) $att['character_id'] : null;
            $name = isset($att['external_name']) && $att['external_name'] !== '' ? (string) $att['external_name'] : null;

            if (($userId === null && $name === null) || ($userId !== null && $name !== null)) {
                return null;
            }
            if ($userId !== null) {
                if (isset($seenUserIds[$userId])) {
                    continue; // same member present in both attendees and recipient_ids
                }
                $seenUserIds[$userId] = true;
                $userIdsToCheck[] = $userId;
            }
            $rows[] = ['user_id' => $userId, 'character_id' => $charId, 'external_name' => $name];
        }

        // Resolve which user_ids actually belong to this CP. Users that
        // do not belong are treated as externals (kept in the report but
        // flagged so the leader can pay them outside the system).
        $cpUserIds = User::whereIn('id', $userIdsToCheck)
            ->where('cp_id', $cpId)
            ->where('membership_status', '!=', 'banned')
            ->pluck('id')
            ->all();
        $cpUserSet = array_flip($cpUserIds);

        // character_id must belong to the same user, otherwise drop it.
        $charOwners = [];
        $charIds = array_filter(array_column($rows, 'character_id'));
        if (!empty($charIds)) {
            $charOwners = \App\Contexts\Identity\Domain\Models\Character::whereIn('id', $charIds)
                ->pluck('user_id', 'id')
                ->toArray();
        }

        return array_map(function ($row) use ($cpUserSet, $charOwners) {
            if ($row['user_id'] !== null && !isset($cpUserSet[$row['user_id']])) {
                // Bot user / member of another CP / banned — record as
                // external using the user's display name if we have it.
                $name = User::whereKey($row['user_id'])->value('name') ?? '(unknown)';
                return ['user_id' => null, 'character_id' => null, 'external_name' => $name, 'is_external' => true];
            }
            $charId = $row['character_id'];
            if ($charId !== null && (!isset($charOwners[$charId]) || $charOwners[$charId] !== $row['user_id'])) {
                // Char belongs to another user — drop to default (main).
                $charId = null;
            }
            return [
                'user_id' => $row['user_id'],
                'character_id' => $charId,
                'external_name' => $row['external_name'],
                'is_external' => $row['user_id'] === null,
            ];
        }, $rows);
    }

    /**
     * Resolve cp_share_pct: explicit `cp_share_pct` wins, otherwise fall
     * back to mapping the legacy `adena_distribution` column.
     */
    private function resolveCpSharePct(Request $request, ?int $current = null): int
    {
        if ($request->filled('cp_share_pct')) {
            return max(0, min(100, (int) $request->input('cp_share_pct')));
        }
        if ($request->filled('adena_distribution')) {
            return $request->input('adena_distribution') === 'cp' ? 100 : 0;
        }
        return $current ?? 0;
    }

    /**
     * Mark a report as voided. Permission: admin (any CP) or the CP
     * founder for their own CP. Voided reports stay in the DB (audit
     * preserved) but are excluded from every stock/adena aggregation.
     */
    public function void(Request $request, LootReport $report)
    {
        $request->validate([
            'reason' => 'required|string|min:3|max:255',
        ]);

        $user = $request->user();
        $role = $user->role?->name;
        $isFounder = $report->cp && (int) $report->cp->leader_id === (int) $user->id;
        $isCpLeader = $role === 'cp_leader' && (int) $user->cp_id === (int) $report->cp_id;
        if ($role !== 'admin' && ! $isFounder && ! $isCpLeader) {
            abort(403, 'Solo el admin o los líderes del CP pueden marcar un report como error.');
        }
        if ($report->voided_at) {
            return back()->withErrors(['report' => 'Este report ya fue marcado como error.']);
        }

        DB::transaction(function () use ($report, $request, $user) {
            $report->forceFill([
                'voided_at' => now(),
                'voided_by_user_id' => $user->id,
                'voided_reason' => $request->input('reason'),
            ])->save();

            \App\Contexts\System\Domain\Models\AuditLog::create([
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'user_id' => $user->id,
                'action' => 'LOOT_VOID',
                'old_values' => ['status' => $report->status],
                'new_values' => [
                    'voided_at' => now()->toIso8601String(),
                    'reason' => $request->input('reason'),
                ],
            ]);
        });

        return back()->with('success', 'Report marcado como error. Stock recalculado.');
    }
}
