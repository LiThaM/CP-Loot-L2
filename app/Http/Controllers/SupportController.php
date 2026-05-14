<?php

namespace App\Http\Controllers;

use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\CpRequest;
use App\Mail\NewCpRequestAdminNotification;
use App\Mail\NewTicketAdminNotification;
use App\Mail\TicketAuthorConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function contact(Request $request): RedirectResponse
    {
        $authUser = $request->user();
        $emailRule = $authUser ? 'nullable|string|email|max:255' : 'required|string|email|max:255';

        $data = $request->validate([
            'subject' => 'required|string|max:140',
            'message' => 'required|string|max:5000',
            'email' => $emailRule,
            'name' => 'nullable|string|max:255',
        ]);

        $metadata = [
            'type' => 'support',
            'user_id' => $authUser?->id,
            'user_name' => $authUser?->name,
            'cp_id' => $authUser?->cp_id,
            'role' => $authUser?->role?->name,
            'url' => $request->headers->get('referer'),
            'ip' => $request->ip(),
        ];

        try {
            $ticket = \App\Models\SupportTicket::create([
                'user_id' => $authUser?->id,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'name' => $data['name'] ?? ($authUser?->name ?? null),
                'email' => $data['email'] ?? ($authUser?->email ?? null),
                'metadata' => $metadata,
                'status' => 'open',
            ]);
        } catch (\Throwable $e) {
            Log::error('Support ticket create failed: '.$e->getMessage());

            return back()->with('error', 'No se pudo enviar el mensaje. Inténtalo más tarde.');
        }

        $this->sendSupportTicketEmails($ticket);

        return back()->with('success', 'Mensaje enviado. Lo revisaremos pronto.');
    }

    private function sendSupportTicketEmails(\App\Models\SupportTicket $ticket): void
    {
        $supportTo = config('services.support.mail_to');

        try {
            if ($supportTo) {
                Mail::to($supportTo)->send(new NewTicketAdminNotification($ticket));
            }
        } catch (\Throwable $e) {
            Log::warning('Support contact admin notification failed: '.$e->getMessage(), ['ticket_id' => $ticket->id]);
        }

        $authorEmail = $ticket->email ?: $ticket->user?->email;
        if ($authorEmail && (! $supportTo || $authorEmail !== $supportTo)) {
            try {
                Mail::to($authorEmail)->send(new TicketAuthorConfirmation($ticket));
            } catch (\Throwable $e) {
                Log::warning('Support contact author confirmation failed: '.$e->getMessage(), ['ticket_id' => $ticket->id]);
            }
        }
    }

    public function cpRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cp_name' => 'required|string|max:255',
            'server' => 'nullable|string|max:255',
            'chronicle' => 'nullable|string|in:'.implode(',', $this->chronicles()),
            'leader_name' => 'nullable|string|max:255',
            'contact_email' => 'required|string|email|max:255',
            'message' => 'nullable|string|max:5000',
        ]);

        $inviteCode = Str::random(12);

        $cp = ConstParty::create([
            'leader_id' => null, // First member to register with the code usually claims it
            'name' => $data['cp_name'],
            'server' => $data['server'] ?? null,
            'chronicle' => $data['chronicle'] ?? 'IL',
            'invite_code' => $inviteCode,
        ]);

        $cpRequest = null;
        try {
            $cpRequest = CpRequest::create([
                'cp_name' => $data['cp_name'],
                'server' => $data['server'] ?? null,
                'chronicle' => $data['chronicle'] ?? null,
                'leader_name' => $data['leader_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'message' => $data['message'] ?? null,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('CP Request audit log failed: ' . $e->getMessage());
        }

        $magicLink = route('register', ['invite' => $inviteCode]);

        if ($cpRequest) {
            $this->notifyCpRequestAdmin($cpRequest, $magicLink);
        }

        return back()->with('success', [
            'message' => 'CP Creada exitosamente',
            'link' => $magicLink,
            'invite_code' => $inviteCode,
            'cp_name' => $cp->name,
        ]);
    }

    private function notifyCpRequestAdmin(CpRequest $cpRequest, ?string $inviteLink): void
    {
        $supportTo = config('services.support.mail_to');
        if (! $supportTo) {
            return;
        }

        try {
            Mail::to($supportTo)->send(new NewCpRequestAdminNotification($cpRequest, $inviteLink));
        } catch (\Throwable $e) {
            Log::warning('CP request admin notification failed: '.$e->getMessage(), [
                'cp_request_id' => $cpRequest->id,
            ]);
        }
    }

    public function approveCpRequest(Request $request, CpRequest $cpRequest): RedirectResponse
    {
        $authUser = $request->user();
        if (! $authUser || ($authUser->role?->name !== 'admin')) {
            abort(403, 'Unauthorized action.');
        }

        if ($cpRequest->status !== 'pending') {
            return back()->with('error', 'Esta solicitud ya no está pendiente.');
        }

        $inviteCode = Str::random(12);

        $cp = null;
        DB::transaction(function () use ($cpRequest, $authUser, $inviteCode, &$cp) {
            $cp = ConstParty::create([
                'leader_id' => null,
                'name' => $cpRequest->cp_name,
                'server' => $cpRequest->server,
                'chronicle' => $cpRequest->chronicle ?: 'IL',
                'invite_code' => $inviteCode,
            ]);

            $cpRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_user_id' => $authUser->id,
            ]);
        });

        $magicLink = route('register', ['invite' => $inviteCode]);

        return back()->with('success', [
            'message' => 'CP creada desde solicitud.',
            'link' => $magicLink,
            'cp_name' => $cp?->name,
        ]);
    }

    public function rejectCpRequest(Request $request, CpRequest $cpRequest): RedirectResponse
    {
        $authUser = $request->user();
        if (! $authUser || ($authUser->role?->name !== 'admin')) {
            abort(403, 'Unauthorized action.');
        }

        if ($cpRequest->status !== 'pending') {
            return back()->with('error', 'Esta solicitud ya no está pendiente.');
        }

        $cpRequest->update([
            'status' => 'rejected',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        return back()->with('success', 'Solicitud rechazada.');
    }

    private function chronicles(): array
    {
        return ['C1', 'C2', 'C3', 'C4', 'C5', 'IL', 'CT1', 'GF', 'HB', 'Classic', 'LU4'];
    }
}
