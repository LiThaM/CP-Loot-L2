<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\System\Domain\Models\ChangelogEntry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $audience = $request->query('audience', 'bot');
        if (!in_array($audience, ChangelogEntry::AUDIENCES, true)) {
            $audience = 'bot';
        }

        $since = $request->query('since');

        $query = ChangelogEntry::query()
            ->forAudience($audience)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->limit(50);

        if ($since && preg_match('/^[\w.\-+]+$/', $since)) {
            // Strict version filtering at the application layer: include only
            // entries with version > since (or version is null, which we drop).
            $entries = $query->get()->filter(function (ChangelogEntry $e) use ($since) {
                if (!$e->version) {
                    return false;
                }
                return version_compare($e->version, $since, '>');
            })->values();
        } else {
            $entries = $query->get();
        }

        return response()->json([
            'audience' => $audience,
            'since' => $since,
            'count' => $entries->count(),
            'entries' => $entries->map(fn (ChangelogEntry $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'version' => $e->version,
                'audience' => $e->audience,
                'title_es' => $e->title_es,
                'title_en' => $e->title_en,
                'body_es' => $e->body_es,
                'body_en' => $e->body_en,
                'published_at' => $e->published_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
