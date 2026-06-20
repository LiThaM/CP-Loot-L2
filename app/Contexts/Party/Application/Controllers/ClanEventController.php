<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanEvent;
use App\Contexts\Party\Domain\Models\ClanEventAttendee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClanEventController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');

        // Load all clan events with approved attendee count
        $allEvents = ClanEvent::where('clan_id', $clan->id)
            ->withCount(['attendees as approved_count' => function ($q) {
                $q->where('status', ClanEventAttendee::STATUS_APPROVED);
            }])
            ->orderByDesc('created_at')
            ->get();

        // Split into past/open events and scheduled (future) events
        $events = $allEvents
            ->filter(fn ($e) => $e->status !== ClanEvent::STATUS_SCHEDULED)
            ->values()
            ->toArray();

        // Scheduled events with RSVP counts and user's RSVP status
        $scheduledIds = $allEvents
            ->filter(fn ($e) => $e->status === ClanEvent::STATUS_SCHEDULED)
            ->pluck('id')
            ->toArray();

        $rsvpCounts = DB::table('clan_event_rsvps')
            ->whereIn('clan_event_id', $scheduledIds)
            ->selectRaw('clan_event_id, response, count(*) as cnt')
            ->groupBy('clan_event_id', 'response')
            ->get()
            ->groupBy('clan_event_id');

        $myRsvps = DB::table('clan_event_rsvps')
            ->where('user_id', $user->id)
            ->whereIn('clan_event_id', $scheduledIds)
            ->pluck('response', 'clan_event_id')
            ->toArray();

        $scheduledEvents = $allEvents
            ->filter(fn ($e) => $e->status === ClanEvent::STATUS_SCHEDULED)
            ->map(function ($e) use ($rsvpCounts) {
                $counts = $rsvpCounts->get($e->id, collect());
                $going = $counts->firstWhere('response', 'going')?->cnt ?? 0;
                $notGoing = $counts->firstWhere('response', 'not_going')?->cnt ?? 0;
                return array_merge($e->toArray(), [
                    'going_count' => $going,
                    'not_going_count' => $notGoing,
                ]);
            })
            ->values()
            ->toArray();

        // CP Impact calculation — efficient: 3 queries total
        $clanMemberships = ClanCpMembership::where('clan_id', $clan->id)
            ->with('constParty')
            ->get();

        $cpIds = $clanMemberships->pluck('cp_id')->toArray();

        // Count finalized events per type
        $totalByType = ClanEvent::where('clan_id', $clan->id)
            ->where('status', ClanEvent::STATUS_FINALIZED)
            ->selectRaw('event_type, count(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type')
            ->toArray();

        $totalRaids  = ($totalByType['raid'] ?? 0) + ($totalByType['epic_raid'] ?? 0);
        $totalSieges = $totalByType['siege'] ?? 0;
        $totalEpics  = $totalByType['epic_raid'] ?? 0;

        // For each finalized event, which CPs had at least one approved attendee?
        $cpAttendance = DB::table('clan_event_attendees as a')
            ->join('clan_events as e', 'e.id', '=', 'a.clan_event_id')
            ->where('e.clan_id', $clan->id)
            ->where('e.status', ClanEvent::STATUS_FINALIZED)
            ->where('a.status', ClanEventAttendee::STATUS_APPROVED)
            ->whereNotNull('a.cp_id')
            ->whereIn('a.cp_id', $cpIds)
            ->selectRaw('a.cp_id, e.event_type, count(distinct e.id) as events_attended')
            ->groupBy('a.cp_id', 'e.event_type')
            ->get()
            ->groupBy('cp_id');

        $maxPossible = ($totalRaids * 1) + ($totalSieges * 2) + ($totalEpics * 3);

        $cpImpact = $clanMemberships->map(function (ClanCpMembership $m) use ($cpAttendance, $totalRaids, $totalSieges, $totalEpics, $maxPossible) {
            $cp = $m->constParty;
            $rows = $cpAttendance->get($m->cp_id, collect());

            $raidsAttended  = 0;
            $siegesAttended = 0;
            $epicsAttended  = 0;

            foreach ($rows as $row) {
                match ($row->event_type) {
                    'raid'      => $raidsAttended  += $row->events_attended,
                    'siege'     => $siegesAttended += $row->events_attended,
                    'epic_raid' => $epicsAttended  += $row->events_attended,
                    default     => null,
                };
            }

            $score = $maxPossible > 0
                ? round((($raidsAttended * 1) + ($siegesAttended * 2) + ($epicsAttended * 3)) / $maxPossible, 4)
                : 0;

            return [
                'cp_id'           => $m->cp_id,
                'cp_name'         => $cp?->name ?? 'CP #' . $m->cp_id,
                'size'            => $cp?->members()->count() ?? 0,
                'raids_attended'  => $raidsAttended,
                'sieges_attended' => $siegesAttended,
                'epics_attended'  => $epicsAttended,
                'total_raids'     => $totalRaids,
                'total_sieges'    => $totalSieges,
                'total_epics'     => $totalEpics,
                'impact_score'    => $score,
            ];
        })->values()->toArray();

        return Inertia::render('Clan/Events', [
            'events'          => $events,
            'scheduledEvents' => $scheduledEvents,
            'cpImpact'        => $cpImpact,
            'myRsvps'         => $myRsvps,
            'userMembership'  => [
                'role'                   => $membership->role,
                'can_approve_attendance' => $membership->can_approve_attendance,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden crear eventos.');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'event_type'   => ['required', 'string', 'in:raid,epic_raid,siege,call_to_arms'],
            'scheduled_at' => ['nullable', 'date'],
            'dkp_reward'   => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $scheduledAt = $data['scheduled_at'] ? now()->parse($data['scheduled_at']) : null;
        $isScheduled = $scheduledAt && $scheduledAt->isFuture();

        $event = new ClanEvent();
        $event->fill([
            'clan_id'      => $clan->id,
            'name'         => $data['name'],
            'event_type'   => $data['event_type'],
            'scheduled_at' => $scheduledAt,
            'dkp_reward'   => $data['dkp_reward'] ?? null,
            'status'       => $isScheduled ? ClanEvent::STATUS_SCHEDULED : ClanEvent::STATUS_OPEN,
            'occurred_at'  => $isScheduled ? null : now(),
        ]);
        $event->forceFill(['created_by_user_id' => $user->id]);
        $event->save();

        return back()->with('success', 'Evento creado.');
    }

    public function show(Request $request, ClanEvent $event): Response
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');

        $event->load([
            'attendees.user',
            'attendees.constParty',
            'attendees.approvedBy',
            'rsvps.user',
            'createdBy',
        ]);

        $myAttendance = $event->attendees->firstWhere('user_id', $user->id);

        $attendees = $event->attendees->map(fn ($a) => [
            'id'            => $a->id,
            'user_id'       => $a->user_id,
            'user_name'     => $a->user?->name,
            'external_name' => $a->external_name,
            'cp_id'         => $a->cp_id,
            'cp_name'       => $a->constParty?->name,
            'status'        => $a->status,
            'approved_by'   => $a->approvedBy?->name,
        ])->values();

        return Inertia::render('Clan/EventDetail', [
            'event'          => $event,
            'attendees'      => $attendees,
            'myAttendance'   => $myAttendance ? [
                'id'     => $myAttendance->id,
                'status' => $myAttendance->status,
            ] : null,
            'myCpId'         => $cp->id,
            'userMembership' => [
                'role'                   => $membership->role,
                'can_approve_attendance' => $membership->can_approve_attendance,
            ],
        ]);
    }

    public function update(Request $request, ClanEvent $event): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden editar eventos.');
        abort_unless($event->status !== ClanEvent::STATUS_FINALIZED, 422, 'No se puede editar un evento finalizado.');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'event_type'   => ['required', 'string', 'in:raid,epic_raid,siege,call_to_arms'],
            'scheduled_at' => ['nullable', 'date'],
            'dkp_reward'   => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $event->update($data);

        return back()->with('success', 'Evento actualizado.');
    }

    public function open(Request $request, ClanEvent $event): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden abrir eventos.');
        abort_unless($event->status === ClanEvent::STATUS_SCHEDULED, 422, 'El evento no está en estado programado.');

        $event->status = ClanEvent::STATUS_OPEN;
        if (!$event->occurred_at) {
            $event->occurred_at = now();
        }
        $event->save();

        return back()->with('success', 'Evento abierto.');
    }

    public function finalize(Request $request, ClanEvent $event): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden finalizar eventos.');
        abort_unless($event->status === ClanEvent::STATUS_OPEN, 422, 'El evento debe estar abierto para finalizarlo.');

        $event->status = ClanEvent::STATUS_FINALIZED;
        $event->save();

        return back()->with('success', 'Evento finalizado. Solo los asistentes aprobados cuentan.');
    }

    public function destroy(Request $request, ClanEvent $event): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($event->clan_id === $clan->id, 403, 'Este evento no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden eliminar eventos.');
        abort_unless($event->status !== ClanEvent::STATUS_FINALIZED, 422, 'No se puede eliminar un evento finalizado.');

        $event->delete();

        return back()->with('success', 'Evento eliminado.');
    }
}
