@php
    $number = $ticket->ticket_number ?: ('#'.$ticket->id);
    $replier = $reply->user;
@endphp

<x-mail.layout :kicker="'Respuesta en ticket ' . $number">
    <h2 style="margin:0 0 6px;font-size:18px;color:#fafafa;">{{ $ticket->subject }}</h2>
    <div style="font-size:12px;font-weight:700;letter-spacing:2px;color:#a78bfa;text-transform:uppercase;margin-bottom:18px;">
        {{ strtoupper($ticket->type ?? 'support') }} · {{ strtoupper($ticket->status ?? 'open') }}
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#0b1220;border:1px solid #1f2937;border-radius:12px;padding:14px 16px;margin-bottom:18px;color:#d1d5db;font-size:13px;">
        <tr><td style="padding:3px 0;color:#9ca3af;width:90px;">Quien responde</td><td style="padding:3px 0;">{{ $replier?->name ?? '—' }} ({{ $replier?->email ?? '—' }})</td></tr>
        <tr><td style="padding:3px 0;color:#9ca3af;">Cuándo</td><td style="padding:3px 0;">{{ optional($reply->created_at)->format('Y-m-d H:i') }}</td></tr>
    </table>

    <div style="font-size:11px;font-weight:700;letter-spacing:2px;color:#9ca3af;text-transform:uppercase;margin-bottom:8px;">Mensaje</div>
    <div style="background:#0b1220;border:1px solid #1f2937;border-radius:12px;padding:14px 16px;white-space:pre-line;color:#e5e7eb;font-size:13px;line-height:1.55;">{{ $reply->message }}</div>

    @if(! empty($reply->attachments) && is_array($reply->attachments))
        <div style="margin-top:18px;font-size:11px;font-weight:700;letter-spacing:2px;color:#9ca3af;text-transform:uppercase;">Adjuntos ({{ count($reply->attachments) }})</div>
        <ul style="padding-left:18px;margin-top:6px;color:#d1d5db;font-size:13px;">
            @foreach($reply->attachments as $att)
                <li>{{ $att['name'] ?? ($att['path'] ?? 'archivo') }}</li>
            @endforeach
        </ul>
    @endif

    <div style="margin-top:24px;text-align:center;">
        <a href="{{ $showUrl }}" style="display:inline-block;padding:10px 22px;border-radius:10px;background:linear-gradient(90deg,#7c3aed,#4f46e5);color:#fff;text-decoration:none;font-weight:800;letter-spacing:2px;text-transform:uppercase;font-size:12px;">
            Ver hilo
        </a>
    </div>
</x-mail.layout>
