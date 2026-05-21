<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\System\Domain\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExternalPayoutsController extends Controller
{
    public function index(Request $request): Response
    {
        $current = $request->user();
        $roleName = $current->role?->name;
        // The superadmin (`admin`) is intentionally locked out — they have no
        // CP to settle. CP leaders see the list AND can mark items paid; CP
        // members see the same list read-only (transparency: they can confirm
        // their leader has paid the externals that farmed with them).
        if ($roleName === 'admin') {
            abort(403, 'Esta página es para miembros de una CP.');
        }
        if (!$current->cp_id || ($current->membership_status ?? 'approved') !== 'approved') {
            abort(403, 'No perteneces a una CP aprobada.');
        }
        $canMarkPaid = $roleName === 'cp_leader';

        $filter = $request->query('filter', 'pending');
        if (!in_array($filter, ['pending', 'paid', 'all'], true)) {
            $filter = 'pending';
        }

        $query = LootReportAttendee::query()
            ->with('lootReport:id,cp_id,event_type,created_at')
            ->where('is_external', true)
            ->whereNotNull('share_adena')
            ->orderByDesc('id')
            ->whereHas('lootReport', fn ($q) => $q->where('cp_id', $current->cp_id));
        if ($filter === 'pending') {
            $query->whereNull('paid_at');
        } elseif ($filter === 'paid') {
            $query->whereNotNull('paid_at');
        }

        $payouts = $query->get()->map(fn ($att) => [
            'id' => $att->id,
            'external_name' => $att->external_name,
            'share_adena' => (int) $att->share_adena,
            'paid_at' => $att->paid_at?->toIso8601String(),
            'sell_report_id' => $att->loot_report_id,
            'sell_report_event' => $att->lootReport?->event_type,
            'sell_report_at' => $att->lootReport?->created_at?->toIso8601String(),
        ]);

        return Inertia::render('System/ExternalPayouts/Index', [
            'payouts' => $payouts,
            'filter' => $filter,
            'canMarkPaid' => $canMarkPaid,
        ]);
    }

    public function markPaid(Request $request, LootReportAttendee $attendee): RedirectResponse
    {
        $current = $request->user();
        $roleName = $current->role?->name;
        if ($roleName !== 'cp_leader') {
            abort(403);
        }

        if (!$attendee->is_external) {
            return back()->withErrors(['attendee' => 'Esa fila no es un pago externo.']);
        }

        $report = $attendee->lootReport;
        if ($report?->cp_id !== $current->cp_id) {
            abort(403);
        }

        if ($attendee->paid_at) {
            return back()->with('info', 'Ya estaba marcado como pagado.');
        }

        $attendee->update(['paid_at' => now()]);

        AuditLog::create([
            'entity_type' => 'LootReportAttendee',
            'entity_id' => $attendee->id,
            'user_id' => $current->id,
            'action' => 'EXTERNAL_PAYOUT_PAID',
            'old_values' => null,
            'new_values' => [
                'external_name' => $attendee->external_name,
                'share_adena' => (int) $attendee->share_adena,
                'sell_report_id' => $attendee->loot_report_id,
            ],
        ]);

        return back()->with('success', 'Pago marcado como liquidado.');
    }
}
