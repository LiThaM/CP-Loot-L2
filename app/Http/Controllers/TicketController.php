<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role?->name;

        $query = SupportTicket::with(['user:id,name', 'assignedTo:id,name', 'replies'])
            ->orderByDesc('created_at');

        if ($role === 'admin') {
            // Admin sees everything
        } elseif ($role === 'cp_leader') {
            // Leader sees: tickets assigned to them + bugs they created
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('user_id', $user->id)
                         ->where('type', 'bug');
                  });
            });
        } else {
            // Members see only their own tickets
            $query->where('user_id', $user->id);
        }

        $tickets = $query->get()->map(fn ($t) => [
            'id'           => $t->id,
            'ticket_number' => $t->ticket_number,
            'subject'      => $t->subject,
            'type'         => $t->type,
            'status'       => $t->status,
            'created_at'   => $t->created_at,
            'closed_at'    => $t->closed_at,
            'creator'      => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name] : null,
            'assigned_to'  => $t->assignedTo ? ['id' => $t->assignedTo->id, 'name' => $t->assignedTo->name] : null,
            'replies_count' => $t->replies->count(),
        ]);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'userRole' => $role,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role?->name;

        // CP Leaders can only create bugs
        $allowedTypes = $role === 'cp_leader'
            ? ['bug']
            : ['bug', 'data_discrepancy'];

        $data = $request->validate([
            'subject' => 'required|string|max:140',
            'message' => 'required|string|max:5000',
            'type'    => 'required|string|in:' . implode(',', $allowedTypes),
        ]);

        // Determine assignment
        $assignedToUserId = null;
        if ($data['type'] === 'data_discrepancy') {
            // Assign to CP leader
            $cp = $user->cp;
            if ($cp && $cp->leader_id) {
                $assignedToUserId = $cp->leader_id;
            }
        }
        // Bugs go to admin (assigned_to = null, admin sees all)

        SupportTicket::create([
            'user_id'              => $user->id,
            'subject'              => $data['subject'],
            'message'              => $data['message'],
            'name'                 => $user->name,
            'email'                => $user->email,
            'status'               => 'open',
            'type'                 => $data['type'],
            'assigned_to_user_id'  => $assignedToUserId,
            'ticket_number'        => SupportTicket::generateTicketNumber(),
            'metadata'             => [
                'user_id'  => $user->id,
                'cp_id'    => $user->cp_id,
                'role'     => $role,
                'ip'       => $request->ip(),
            ],
        ]);

        return redirect()->route('tickets.index')->with('success', 'Ticket creado correctamente.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $user = $request->user();
        $role = $user->role?->name;

        $this->authorizeView($user, $role, $ticket);

        $ticket->load([
            'user:id,name',
            'assignedTo:id,name',
            'replies.user:id,name',
        ]);

        return Inertia::render('Tickets/Show', [
            'ticket'   => [
                'id'            => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject'       => $ticket->subject,
                'message'       => $ticket->message,
                'type'          => $ticket->type,
                'status'        => $ticket->status,
                'created_at'    => $ticket->created_at,
                'closed_at'     => $ticket->closed_at,
                'creator'       => $ticket->user ? ['id' => $ticket->user->id, 'name' => $ticket->user->name] : null,
                'assigned_to'   => $ticket->assignedTo ? ['id' => $ticket->assignedTo->id, 'name' => $ticket->assignedTo->name] : null,
                'replies'       => $ticket->replies->map(fn ($r) => [
                    'id'         => $r->id,
                    'message'    => $r->message,
                    'created_at' => $r->created_at,
                    'user'       => ['id' => $r->user->id, 'name' => $r->user->name],
                    'is_mine'    => $r->user_id === $user->id,
                ]),
            ],
            'userRole'  => $role,
            'canReply'  => ! $ticket->isClosed(),
            'canClose'  => $this->canClose($user, $role, $ticket),
            'isCreator' => $ticket->user_id === $user->id,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role?->name;

        $this->authorizeView($user, $role, $ticket);

        if ($ticket->isClosed()) {
            return back()->with('error', 'El ticket está cerrado.');
        }

        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'message'   => $data['message'],
        ]);

        // Reopen if closed (shouldn't happen but safety net)
        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'open', 'closed_at' => null]);
        }

        return back()->with('success', 'Respuesta enviada.');
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role?->name;

        if (! $this->canClose($user, $role, $ticket)) {
            abort(403);
        }

        $ticket->update(['status' => 'closed', 'closed_at' => now()]);

        return back()->with('success', 'Ticket cerrado.');
    }

    public function reopen(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role?->name;

        if ($role !== 'admin') {
            abort(403);
        }

        $ticket->update(['status' => 'open', 'closed_at' => null]);

        return back()->with('success', 'Ticket reabierto.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function authorizeView($user, string $role, SupportTicket $ticket): void
    {
        if ($role === 'admin') {
            return;
        }

        if ($role === 'cp_leader') {
            // Leader can view tickets assigned to them or bugs they created
            if ($ticket->assigned_to_user_id === $user->id) return;
            if ($ticket->user_id === $user->id && $ticket->type === 'bug') return;
            abort(403);
        }

        // Members can only view their own tickets
        if ($ticket->user_id !== $user->id) {
            abort(403);
        }
    }

    private function canClose($user, string $role, SupportTicket $ticket): bool
    {
        if ($role === 'admin') return true;
        if ($role === 'cp_leader' && $ticket->assigned_to_user_id === $user->id) return true;
        return false;
    }
}
