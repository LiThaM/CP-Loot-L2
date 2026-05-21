<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleasesListController extends Controller
{
    /**
     * GET /api/v1/releases
     *
     * Lists all published releases for the given channel, ordered newest
     * first. Releases whose binary has been purged keep their metadata row
     * (for changelog/history) and are flagged with available=false. The
     * client (or the website) can render the full timeline without breaking
     * when older .zip blobs no longer exist in storage.
     *
     * Query params:
     *   channel         stable|beta — defaults to stable.
     *   include_purged  true to include releases whose binary was purged.
     *                   Defaults to false: only physically downloadable
     *                   releases are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $channel = $request->query('channel', 'stable');
        if (!in_array($channel, ['stable', 'beta'], true)) {
            $channel = 'stable';
        }

        $includePurged = filter_var(
            $request->query('include_purged', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $query = Release::published()
            ->channel($channel)
            ->orderByDesc('released_at')
            ->orderByDesc('id');

        if (!$includePurged) {
            $query->withBinary();
        }

        $releases = $query->get();

        return response()->json([
            'channel' => $channel,
            'count' => $releases->count(),
            'items' => $releases->map(function (Release $r) {
                $available = $r->isBinaryAvailable();
                return [
                    'version' => $r->version,
                    'name' => $r->name,
                    'channel' => $r->channel,
                    'sha256' => $r->sha256,
                    'size_bytes' => $r->size_bytes,
                    'critical_update' => (bool) $r->critical_update,
                    'min_supported_version' => $r->min_supported_version,
                    'released_at' => optional($r->released_at)->toIso8601String(),
                    'published_at' => optional($r->published_at)->toIso8601String(),
                    'download_count' => (int) $r->download_count,
                    'available' => $available,
                    'purged_at' => optional($r->binary_purged_at)->toIso8601String(),
                    'download_url' => $available
                        ? url('/api/v1/releases/'.$r->version.'/download')
                        : null,
                    'release_notes' => $r->release_notes_es
                        ?: $r->release_notes_en
                        ?: $r->release_notes_md,
                    'release_notes_es' => $r->release_notes_es ?: $r->release_notes_md,
                    'release_notes_en' => $r->release_notes_en ?: $r->release_notes_md,
                ];
            })->all(),
        ]);
    }
}
