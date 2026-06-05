<x-mail.layout :kicker="$kicker" :title="$title">
    <h1 style="margin:0 0 16px 0;font-size:22px;font-weight:900;color:#f3f4f6;letter-spacing:-0.01em;">
        {{ $title }}
    </h1>

    @if($bodyHtml)
        <div style="color:#cbd5e1;font-size:14px;line-height:1.6;">
            {!! $bodyHtml !!}
        </div>
    @endif

    <div style="margin-top:28px;">
        <a href="{{ $changelogUrl }}"
           style="display:inline-block;padding:11px 22px;background:linear-gradient(90deg,#7c3aed,#4f46e5);color:#ffffff;text-decoration:none;font-weight:800;font-size:13px;letter-spacing:1.5px;text-transform:uppercase;border-radius:8px;">
            {{ $ctaText }}
        </a>
    </div>

    <div style="margin-top:36px;padding-top:18px;border-top:1px solid #1f2937;color:#6b7280;font-size:11px;line-height:1.5;">
        {{ $unsubscribeText }}
        <a href="{{ $profileUrl }}" style="color:#a78bfa;text-decoration:none;">/profile</a>
    </div>
</x-mail.layout>
