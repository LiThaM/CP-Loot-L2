<?php

namespace App\Http\Controllers;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\CpRequest;
use App\Mail\NewCpRequestAdminNotification;
use App\Mail\NewTicketAdminNotification;
use App\Mail\TicketAuthorConfirmation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

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
        // Single funnel: visitor submits CP details AND their account
        // credentials in one form. Backend transactionally creates the
        // user, the CP, and binds the user as cp_leader. Auto-login on
        // success — no more "magic link → register later" two-step that
        // could leave the CP orphaned if the requester never returned.
        $data = $request->validate([
            'cp_name' => 'required|string|max:255',
            'server' => 'nullable|string|max:255',
            'chronicle' => 'nullable|string|in:'.implode(',', $this->chronicles()),
            'leader_name' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'message' => 'nullable|string|max:5000',
        ]);

        $leaderRole = Role::where('name', 'cp_leader')->first();
        if (! $leaderRole) {
            throw ValidationException::withMessages([
                'cp_name' => 'System error: Required role cp_leader is missing.',
            ]);
        }

        $inviteCode = Str::random(12);
        $user = null;
        $cp = null;
        $cpRequest = null;

        // Capture the locale already resolved by HandleInertiaRequests
        // (?lang= query > session > cookie > app fallback). Persisting it
        // on both the new user and the cp_requests audit row means future
        // transactional mails (changelog, reminders) hit the right language.
        $locale = app()->getLocale();
        $preferredLanguage = in_array($locale, ['es', 'en'], true) ? $locale : null;

        DB::transaction(function () use ($data, $inviteCode, $leaderRole, $preferredLanguage, &$user, &$cp, &$cpRequest) {
            $cp = ConstParty::create([
                'leader_id' => null,
                'name' => $data['cp_name'],
                'server' => $data['server'] ?? null,
                'chronicle' => $data['chronicle'] ?? 'IL',
                'invite_code' => $inviteCode,
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'cp_id' => $cp->id,
                'membership_status' => 'approved',
                'language_preference' => $preferredLanguage ?: 'system',
            ]);
            $user->forceFill(['role_id' => $leaderRole->id])->save();

            $cp->forceFill(['leader_id' => $user->id])->save();

            $cpRequest = CpRequest::create([
                'cp_name' => $data['cp_name'],
                'server' => $data['server'] ?? null,
                'chronicle' => $data['chronicle'] ?? null,
                'leader_name' => $data['leader_name'] ?? null,
                'contact_email' => $data['email'],
                'preferred_language' => $preferredLanguage,
                'message' => $data['message'] ?? null,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        });

        if ($cpRequest) {
            $this->notifyCpRequestAdmin($cpRequest, route('register', ['invite' => $inviteCode]));
        }

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('dashboard');
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
