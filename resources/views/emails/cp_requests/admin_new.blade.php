<x-mail.layout kicker="Nueva CP solicitada">
    <h2 style="margin:0 0 6px;font-size:18px;color:#fafafa;">{{ $cpRequest->cp_name }}</h2>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#0b1220;border:1px solid #1f2937;border-radius:12px;padding:14px 16px;margin:14px 0 18px;color:#d1d5db;font-size:13px;">
        @if($cpRequest->server)
            <tr><td style="padding:3px 0;color:#9ca3af;width:120px;">Servidor</td><td style="padding:3px 0;">{{ $cpRequest->server }}</td></tr>
        @endif
        @if($cpRequest->chronicle)
            <tr><td style="padding:3px 0;color:#9ca3af;">Chronicle</td><td style="padding:3px 0;">{{ $cpRequest->chronicle }}</td></tr>
        @endif
        <tr><td style="padding:3px 0;color:#9ca3af;">Líder</td><td style="padding:3px 0;">{{ $cpRequest->leader_name ?: '—' }}</td></tr>
        <tr><td style="padding:3px 0;color:#9ca3af;">Email</td><td style="padding:3px 0;">{{ $cpRequest->contact_email ?: '—' }}</td></tr>
        <tr><td style="padding:3px 0;color:#9ca3af;">Estado</td><td style="padding:3px 0;">{{ strtoupper($cpRequest->status ?? '—') }}</td></tr>
        <tr><td style="padding:3px 0;color:#9ca3af;">Recibida</td><td style="padding:3px 0;">{{ optional($cpRequest->created_at)->format('Y-m-d H:i') }}</td></tr>
    </table>

    @if($cpRequest->message)
        <div style="font-size:11px;font-weight:700;letter-spacing:2px;color:#9ca3af;text-transform:uppercase;margin-bottom:8px;">Mensaje</div>
        <div style="background:#0b1220;border:1px solid #1f2937;border-radius:12px;padding:14px 16px;white-space:pre-line;color:#e5e7eb;font-size:13px;line-height:1.55;">{{ $cpRequest->message }}</div>
    @endif

    @if($inviteLink)
        <div style="margin-top:24px;text-align:center;">
            <a href="{{ $inviteLink }}" style="display:inline-block;padding:10px 22px;border-radius:10px;background:linear-gradient(90deg,#7c3aed,#4f46e5);color:#fff;text-decoration:none;font-weight:800;letter-spacing:2px;text-transform:uppercase;font-size:12px;">
                Enlace de invitación
            </a>
        </div>
    @endif
</x-mail.layout>
