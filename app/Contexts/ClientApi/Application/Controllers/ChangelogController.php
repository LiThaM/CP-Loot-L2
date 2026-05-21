<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Software (desktop client) changelog feed. Backed by the releases table —
 * the AdenaLedger web app has its own changelog living in changelog_entries
 * and the two are intentionally kept separate so they don't bleed into each
 * other on the public landing or in the desktop client's update dialog.
 */
class ChangelogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $since = $request->query('since');
        $channel = $request->query('channel');

        $query = Release::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('released_at')
            ->limit(50);

        if ($channel && in_array($channel, ['stable', 'beta'], true)) {
            $query->where('channel', $channel);
        }

        $releases = $query->get();

        if ($since && preg_match('/^[\w.\-+]+$/', $since)) {
            $releases = $releases->filter(fn (Release $r) => version_compare($r->version, $since, '>'))->values();
        }

        return response()->json([
            'since' => $since,
            'channel' => $channel,
            'count' => $releases->count(),
            'entries' => $releases->map(fn (Release $r) => [
                'id' => $r->id,
                'version' => $r->version,
                'channel' => $r->channel,
                'critical_update' => (bool) $r->critical_update,
                'released_at' => $r->released_at?->toIso8601String(),
                'notes_es' => $r->release_notes_es ?: $r->release_notes_md,
                'notes_en' => $r->release_notes_en ?: $r->release_notes_md,
            ])->all(),
        ]);
    }
}
