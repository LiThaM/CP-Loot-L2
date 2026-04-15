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
        $supportEmail = (string) env('SUPPORT_EMAIL', 'support@adenaledger.com');

        $body = collect([
            'type' => 'support',
            'subject' => $data['subject'],
            'message' => $data['message'],
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'user_id' => $authUser?->id,
            'user_name' => $authUser?->name,
            'cp_id' => $authUser?->cp_id,
            'role' => $authUser?->role?->name,
            'url' => $request->headers->get('referer'),
            'ip' => $request->ip(),
        ])->filter(fn ($v) => $v !== null)->map(fn ($v, $k) => $k.': '.$v)->implode("\n");

        Mail::raw($body, function ($mail) use ($supportEmail, $data) {
            $mail->to($supportEmail)->subject('[AdenaLedger] Soporte: '.$data['subject']);
            if (! empty($data['email'])) {
                $mail->replyTo($data['email']);
            }
        });

        return back()->with('success', 'Mensaje enviado a soporte.');
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

        $cpRequest = null;
        $createFailed = false;
        $createError = null;

        try {
            $cpRequest = CpRequest::create([
                'cp_name' => $data['cp_name'],
                'server' => $data['server'] ?? null,
                'chronicle' => $data['chronicle'] ?? null,
                'leader_name' => $data['leader_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'message' => $data['message'] ?? null,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            $createFailed = true;
            $createError = $e->getMessage();
        }

        $supportEmail = (string) env('SUPPORT_EMAIL', 'support@adenaledger.com');
        $body = collect([
            'type' => 'cp_request',
            'db_create' => $createFailed ? 'failed' : 'ok',
            'db_error' => $createFailed ? $createError : null,
            'request_id' => $cpRequest?->id,
            'cp_name' => $cpRequest?->cp_name ?? $data['cp_name'],
            'server' => $cpRequest?->server ?? ($data['server'] ?? null),
            'chronicle' => $cpRequest?->chronicle ?? ($data['chronicle'] ?? null),
            'leader_name' => $cpRequest?->leader_name ?? ($data['leader_name'] ?? null),
            'contact_email' => $cpRequest?->contact_email ?? $data['contact_email'],
            'message' => $cpRequest?->message ?? ($data['message'] ?? null),
            'url' => $request->headers->get('referer'),
            'ip' => $request->ip(),
        ])->filter(fn ($v) => $v !== null)->map(fn ($v, $k) => $k.': '.$v)->implode("\n");

        Mail::raw($body, function ($mail) use ($supportEmail, $cpRequest, $data, $createFailed) {
            $subject = $cpRequest
                ? '[AdenaLedger] Solicitud alta CP #'.$cpRequest->id
                : '[AdenaLedger] Solicitud alta CP (sin guardar en BD)';
            if ($createFailed) {
                $subject .= ' [DB ERROR]';
            }

            $mail->to($supportEmail)->subject($subject);
            $mail->replyTo($data['contact_email']);
        });

        if ($createFailed) {
            return back()->with('error', 'No se pudo registrar la solicitud. Se ha notificado a soporte.');
        }

        return back()->with('success', 'Solicitud enviada. Te contactaremos con el link de invitación.');
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
