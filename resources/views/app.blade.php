@php
    // SEO defaults that get served BEFORE Vue mounts. Social crawlers
    // (Facebook, X, Discord, LinkedIn, WhatsApp, Telegram, Slack) and
    // Googlebot's first pass read these — Inertia's client-side `<Head>`
    // arrives too late for them. Per-locale copy keeps the meta in
    // step with the page the user actually sees.
    $locale = app()->getLocale();

    // Same resolution order as HandleInertiaRequests::share() (query > session >
    // cookie), but falling back to the browser's Accept-Language header instead of
    // the static APP_LOCALE default. Needed because a first-time visitor with no
    // session/cookie yet would otherwise always get $locale === 'en': the app only
    // ever detects the browser language client-side (Welcome.vue / MainLayout.vue),
    // after a round trip that doesn't run in time for this initial response.
    $requestedLocale = request()->query('lang')
        ?: (request()->session()->get('locale') ?: request()->cookie('locale'));
    $widgetLocale = (is_string($requestedLocale) && in_array($requestedLocale, ['en', 'es', 'it', 'ru'], true))
        ? $requestedLocale
        : request()->getPreferredLanguage(['en', 'es', 'it', 'ru']);

    $appName = (string) config('app.name', 'AdenaLedger');
    $appUrl = rtrim((string) config('app.url', request()->getSchemeAndHttpHost()), '/');
    $currentUrl = $appUrl . request()->getRequestUri();
    $ogImage = $appUrl . '/og-image.png';

    $copy = [
        'es' => [
            'tagline' => 'Loot · Adena · Warehouse para CPs de Lineage II',
            'title' => 'AdenaLedger · Loot, Adena y Warehouse para Constructed Parties de L2',
            'description' => 'Gestiona el loot, reparte adena y controla el warehouse de tu CP con auditoría real. Gratis, sin anuncios, sin tracking.',
        ],
        'en' => [
            'tagline' => 'Loot · Adena · Warehouse for Lineage II CPs',
            'title' => 'AdenaLedger · Loot, Adena and Warehouse for L2 Constructed Parties',
            'description' => 'Track loot, split adena and manage your CP warehouse with a real audit trail. Free, no ads, no tracking.',
        ],
    ];
    $c = $copy[$locale] ?? $copy['en'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0a0a0f">

        <title inertia>{{ $c['title'] }}</title>
        <meta name="description" content="{{ $c['description'] }}">

        {{-- Canonical + alternates so Google doesn't treat ES/EN as duplicates --}}
        <link rel="canonical" href="{{ $currentUrl }}">
        <link rel="alternate" hreflang="es" href="{{ $appUrl }}/?lang=es">
        <link rel="alternate" hreflang="en" href="{{ $appUrl }}/?lang=en">
        <link rel="alternate" hreflang="x-default" href="{{ $appUrl }}/">

        {{-- Open Graph (Facebook / WhatsApp / Discord / LinkedIn / Telegram) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="{{ $c['title'] }}">
        <meta property="og:description" content="{{ $c['description'] }}">
        <meta property="og:url" content="{{ $currentUrl }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $appName }} — {{ $c['tagline'] }}">
        <meta property="og:locale" content="{{ $locale === 'es' ? 'es_ES' : 'en_US' }}">
        <meta property="og:locale:alternate" content="{{ $locale === 'es' ? 'en_US' : 'es_ES' }}">

        {{-- Twitter / X --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $c['title'] }}">
        <meta name="twitter:description" content="{{ $c['description'] }}">
        <meta name="twitter:image" content="{{ $ogImage }}">
        <meta name="twitter:image:alt" content="{{ $appName }} — {{ $c['tagline'] }}">

        {{-- Icons + PWA manifest --}}
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" href="/images/favicon.png" sizes="any">
        <link rel="apple-touch-icon" href="/images/favicon.png">
        <link rel="manifest" href="/manifest.webmanifest">

        {{-- Fonts --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|cinzel:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        {{-- Structured data for the homepage. SoftwareApplication signals
             Google that this is a webapp/product; rich snippets eligible. --}}
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $appName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, Windows',
            'description' => $c['description'],
            'url' => $appUrl,
            'image' => $ogImage,
            'inLanguage' => ['es', 'en'],
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
            ],
            'author' => [
                '@type' => 'Organization',
                'name' => $appName,
                'url' => $appUrl,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>

        {{-- Scripts --}}
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Friday support widget (Shadow DOM chat widget, adenaledger.com project).
             Hidden below the `lg` breakpoint (1024px): MainLayout's mobile bottom-nav
             is fixed to the same bottom-right corner (`lg:hidden`), and the widget's
             own CSS lives inside a closed-off shadow tree we can't reposition from
             here — so instead of colliding, it only mounts on desktop widths where
             there's no bottom-nav. `data-lang` uses $widgetLocale (query/session/
             cookie, else Accept-Language) computed above — same resolution the rest
             of the site uses, so the widget doesn't default to English for a
             first-time non-English visitor. `data-suggestions` is left unset so it
             falls back to the widget's own per-locale suggestion chips. --}}
        <style>
            @media (max-width: 1023px) {
                #friday-support-widget { display: none !important; }
            }
        </style>
        <script src="https://friday.adenaledger.com/api/support/widget.js"
                data-project="adenaledger"
                data-key="pk_t5JlTv-hVsqBTPDnJJ85NDeCE_AP7ww3"
                data-name="AdenaLedger"
                data-lang="{{ $widgetLocale }}"
                async></script>
    </body>
</html>
