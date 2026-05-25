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

        // Software (desktop) changelog: each published release is one entry.
        // Web app changelog lives in the changelog_entries table — kept fully
        // separate so the two stop bleeding into each other.
        $releaseHistory = Release::published()
            ->orderByDesc('released_at')
            ->limit(10)
            ->get(['id', 'version', 'channel', 'critical_update', 'release_notes_md', 'release_notes_es', 'release_notes_en', 'released_at']);

        $webChangelog = ChangelogEntry::query()
            ->whereIn('audience', ['web', 'both'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'type', 'title_es', 'title_en', 'body_es', 'body_en', 'published_at']);

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
            'webChangelog' => $webChangelog->map(fn (ChangelogEntry $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'title_es' => $e->title_es,
                'title_en' => $e->title_en,
                'body_es' => $e->body_es,
                'body_en' => $e->body_en,
                'published_at' => $e->published_at?->toIso8601String(),
            ]),
        ]);
    }
}
