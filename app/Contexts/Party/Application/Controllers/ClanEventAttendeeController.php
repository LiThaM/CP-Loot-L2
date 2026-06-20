<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanEvent;
use App\Contexts\Party\Domain\Models\ClanEventAttendee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClanEventAttendeeController extends Controller
{
    public function store(Request $request, ClanEvent $event): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($event->status === ClanEvent::STATUS_OPEN, 422, 'El evento no está abierto para asistencia.');

        $already = ClanEventAttendee::where('clan_event_id', $event->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($already) {
            return back()->with('error', 'Ya tienes un registro de asistencia para este evento.');
        }

        ClanEventAttendee::create([
            'clan_event_id' => $event->id,
            'user_id'       => $user->id,
            'cp_id'         => $cp->id,
            'status'        => ClanEventAttendee::STATUS_PENDING,
        ]);

        return back()->with('success', 'Asistencia registrada, pendiente de aprobación.');
    }

    public function approve(Request $request, ClanEvent $event, ClanEventAttendee $attendee): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($attendee->clan_event_id === $event->id, 403, 'Este asistente no pertenece a este evento.');

        $canApprove = $membership->isAdmin()
            || ($membership->can_approve_attendance && $cp && $attendee->cp_id === $cp->id);

        abort_unless($canApprove, 403, 'No tienes permiso para aprobar/rechazar esta asistencia.');

        $data = $request->validate([
            'action' => ['required', 'string', 'in:approved,rejected'],
        ]);

        $attendee->status = $data['action'];
        $attendee->approved_by_user_id = $user->id;
        $attendee->save();

        $label = $data['action'] === 'approved' ? 'aprobada' : 'rechazada';

        return back()->with('success', "Asistencia {$label}.");
    }

    public function destroy(Request $request, ClanEvent $event, ClanEventAttendee $attendee): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($attendee->clan_event_id === $event->id, 403, 'Este asistente no pertenece a este evento.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden eliminar asistentes.');
        abort_unless($event->status !== ClanEvent::STATUS_FINALIZED, 422, 'No se puede modificar un evento finalizado.');

        $attendee->delete();

        return back()->with('success', 'Asistente eliminado.');
    }

    public function storeExternal(Request $request, ClanEvent $event): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden añadir asistentes externos.');

        $data = $request->validate([
            'external_name' => ['required', 'string', 'max:60'],
        ]);

        ClanEventAttendee::create([
            'clan_event_id' => $event->id,
            'user_id'       => null,
            'external_name' => $data['external_name'],
            'cp_id'         => null,
            'status'        => ClanEventAttendee::STATUS_APPROVED,
        ]);

        return back()->with('success', 'Asistente externo añadido.');
    }
}
