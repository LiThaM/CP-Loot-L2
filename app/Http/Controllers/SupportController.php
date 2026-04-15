<?php

namespace App\Http\Controllers;

use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\CpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function contact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:140',
            'message' => 'required|string|max:5000',
            'email' => 'nullable|string|email|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $authUser = $request->user();

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
            \App\Models\SupportTicket::create([
                'user_id' => $authUser?->id,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'name' => $data['name'] ?? ($authUser?->name ?? null),
                'email' => $data['email'] ?? ($authUser?->email ?? null),
                'metadata' => $metadata,
                'status' => 'open',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Support ticket create failed: '.$e->getMessage());

            return back()->with('error', 'No se pudo enviar el mensaje. Inténtalo más tarde.');
        }

        return back()->with('success', 'Mensaje enviado. Lo revisaremos pronto.');
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

        try {
            CpRequest::create([
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
            \Illuminate\Support\Facades\Log::error('CP Request audit log failed: ' . $e->getMessage());
        }

        $magicLink = route('register', ['invite' => $inviteCode]);

        return back()->with('success', [
            'message' => 'CP Creada exitosamente',
            'link' => $magicLink,
            'invite_code' => $inviteCode,
            'cp_name' => $cp->name,
        ]);
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
        return ['C1', 'C2', 'C3', 'C4', 'C5', 'IL', 'HB', 'Classic', 'LU4'];
    }
}
