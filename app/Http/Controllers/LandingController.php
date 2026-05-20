<?php

namespace App\Http\Controllers;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Contexts\System\Domain\Models\ChangelogEntry;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function show(): Response
    {
        $latest = Release::published()
            ->channel('stable')
            ->orderByDesc('released_at')
            ->first();

        $changelog = ChangelogEntry::forAudience('bot')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'type', 'version', 'title_es', 'title_en', 'body_es', 'body_en', 'published_at']);

        return Inertia::render('Landing', [
            'latest' => $latest ? [
                'version' => $latest->version,
                'sha256' => $latest->sha256,
                'size_bytes' => $latest->size_bytes,
                'released_at' => $latest->released_at?->toIso8601String(),
                'critical_update' => (bool) $latest->critical_update,
                'download_url' => $latest->storage_path
                    ? url('/api/v1/releases/'.$latest->version.'/download')
                    : null,
            ] : null,
            'changelog' => $changelog,
        ]);
    }
}
