<?php

namespace App\Http\Controllers;

use App\Contexts\ClientApi\Domain\Models\Release;
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

        // /download is exclusively about the desktop bot client: latest
        // build + release history. The web app changelog lives in
        // changelog_entries and is rendered on the homepage and /changelog —
        // it has no place here, the two are intentionally separate.
        $releaseHistory = Release::published()
            ->orderByDesc('released_at')
            ->limit(10)
            ->get(['id', 'version', 'channel', 'critical_update', 'release_notes_md', 'release_notes_es', 'release_notes_en', 'released_at']);

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
            'releases' => $releaseHistory->map(fn (Release $r) => [
                'id' => $r->id,
                'version' => $r->version,
                'channel' => $r->channel,
                'critical_update' => (bool) $r->critical_update,
                'released_at' => $r->released_at?->toIso8601String(),
                'notes_es' => $r->release_notes_es ?: $r->release_notes_md,
                'notes_en' => $r->release_notes_en ?: $r->release_notes_md,
            ]),
        ]);
    }
}
