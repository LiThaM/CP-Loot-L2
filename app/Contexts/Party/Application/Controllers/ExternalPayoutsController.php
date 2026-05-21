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
        if (! in_array($roleName, ['admin', 'cp_leader'], true)) {
            abort(403, 'Solo el líder o admin pueden ver los pagos externos.');
        }

        $filter = $request->query('filter', 'pending');
        if (!in_array($filter, ['pending', 'paid', 'all'], true)) {
            $filter = 'pending';
        }

        $query = LootReportAttendee::query()
            ->with('lootReport:id,cp_id,event_type,created_at')
            ->where('is_external', true)
            ->whereNotNull('share_adena')
            ->orderByDesc('id');

        if ($roleName !== 'admin') {
            // Leader sees only their own CP; admin sees all CPs.
            $query->whereHas('lootReport', fn ($q) => $q->where('cp_id', $current->cp_id));
        }
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
        ]);
    }

    public function markPaid(Request $request, LootReportAttendee $attendee): RedirectResponse
    {
        $current = $request->user();
        $roleName = $current->role?->name;
        if (! in_array($roleName, ['admin', 'cp_leader'], true)) {
            abort(403);
        }

        if (!$attendee->is_external) {
            return back()->withErrors(['attendee' => 'Esa fila no es un pago externo.']);
        }

        $report = $attendee->lootReport;
        if ($roleName !== 'admin' && $report?->cp_id !== $current->cp_id) {
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
