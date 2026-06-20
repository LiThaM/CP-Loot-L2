<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanEvent;
use App\Contexts\Party\Domain\Models\ClanEventRsvp;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClanEventRsvpController extends Controller
{
    public function store(Request $request, ClanEvent $event): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($event->status === ClanEvent::STATUS_SCHEDULED, 422, 'Solo puedes confirmar asistencia en eventos programados.');

        $data = $request->validate([
            'response' => ['required', 'string', 'in:going,not_going'],
        ]);

        ClanEventRsvp::updateOrCreate(
            ['clan_event_id' => $event->id, 'user_id' => $user->id],
            ['response' => $data['response']]
        );

        $label = $data['response'] === 'going' ? 'Confirmada' : 'Marcada como no asistirá';

        return back()->with('success', "{$label} tu asistencia.");
    }

    public function destroy(Request $request, ClanEvent $event): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');

        ClanEventRsvp::where('clan_event_id', $event->id)
            ->where('user_id', $user->id)
            ->delete();

        return back()->with('success', 'RSVP cancelado.');
    }
}
