@php
    $number = $ticket->ticket_number ?: ('#'.$ticket->id);
@endphp

<x-mail.layout :kicker="'Ticket ' . $number">
    <p style="margin:0 0 14px;">Hola {{ $ticket->name ?: ($ticket->user?->name ?? '') }},</p>
    <p style="margin:0 0 14px;">Hemos recibido tu ticket <strong>{{ $number }}</strong> y lo revisaremos lo antes posible. Te avisaremos por email cuando haya respuesta.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#0b1220;border:1px solid #1f2937;border-radius:12px;padding:14px 16px;margin-bottom:18px;color:#d1d5db;font-size:13px;">
        <tr><td style="padding:3px 0;color:#9ca3af;width:90px;">Asunto</td><td style="padding:3px 0;">{{ $ticket->subject }}</td></tr>
        <tr><td style="padding:3px 0;color:#9ca3af;">Tipo</td><td style="padding:3px 0;">{{ strtoupper($ticket->type ?? 'support') }}</td></tr>
        <tr><td style="padding:3px 0;color:#9ca3af;">Recibido</td><td style="padding:3px 0;">{{ optional($ticket->created_at)->format('Y-m-d H:i') }}</td></tr>
    </table>

    <div style="font-size:11px;font-weight:700;letter-spacing:2px;color:#9ca3af;text-transform:uppercase;margin-bottom:8px;">Tu mensaje</div>
    <div style="background:#0b1220;border:1px solid #1f2937;border-radius:12px;padding:14px 16px;white-space:pre-line;color:#e5e7eb;font-size:13px;line-height:1.55;">{{ $ticket->message }}</div>

    @if($showUrl)
        <div style="margin-top:24px;text-align:center;">
            <a href="{{ $showUrl }}" style="display:inline-block;padding:10px 22px;border-radius:10px;background:linear-gradient(90deg,#7c3aed,#4f46e5);color:#fff;text-decoration:none;font-weight:800;letter-spacing:2px;text-transform:uppercase;font-size:12px;">
                Ver ticket
            </a>
        </div>
    @endif
</x-mail.layout>
