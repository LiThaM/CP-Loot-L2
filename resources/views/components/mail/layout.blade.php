@props(['kicker' => null, 'title' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#0f172a;font-family:'Helvetica Neue',Arial,sans-serif;color:#e5e7eb;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#0f172a;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#111827;border:1px solid #1f2937;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;border-bottom:1px solid #1f2937;background:linear-gradient(90deg,#581c87,#3730a3);">
                            <div style="font-size:18px;font-weight:900;letter-spacing:3px;color:#e9d5ff;text-transform:uppercase;">
                                {{ config('app.name') }}
                            </div>
                            @if($kicker)
                                <div style="font-size:11px;font-weight:700;letter-spacing:2px;color:#c4b5fd;text-transform:uppercase;margin-top:4px;">
                                    {{ $kicker }}
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;color:#e5e7eb;font-size:14px;line-height:1.55;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid #1f2937;background:#0b1220;color:#6b7280;font-size:11px;text-align:center;">
                            {{ config('app.name') }} · {{ config('app.url') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
