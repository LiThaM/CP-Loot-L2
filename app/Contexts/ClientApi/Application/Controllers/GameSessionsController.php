<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Application\Requests\SubmitGameSessionRequest;
use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\ClientApi\Domain\Models\GameSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Sesiones de farmeo hacia la web (bug G de bugsApi/BUGS.md) — la pieza
 * que conecta la app con la plataforma: adenaledger.com pinta perfiles y
 * rankings por personaje a partir de esto.
 *
 *   POST /api/v1/sessions             — el cliente sube el resumen al
 *                                       cerrar sesión (client_key+anon).
 *   GET  /api/v1/chars/{name}/sessions — público, para la web.
 *
 * El cliente reintenta desde un outbox local al arrancar (mismo patrón
 * que los OCR samples), así que el POST es idempotente: mismo install +
 * char + started_at → 200 duplicate con el id existente, no fila nueva.
 */
class GameSessionsController extends Controller
{
    public const MAX_PAGE_SIZE = 100;

    public function store(SubmitGameSessionRequest $request): JsonResponse
    {
        /** @var AnonToken|null $anon */
        $anon = $request->attributes->get('anon_token');

        $char = (string) $request->input('char');
        $startedAt = Carbon::parse($request->input('started_at'));

        $existing = GameSession::query()
            ->where('anon_token_id', $anon?->id)
            ->where('char_name', $char)
            ->where('started_at', $startedAt)
            ->first();

        if ($existing !== null) {
            return response()->json([
                'status' => 'duplicate',
                'session_id' => $existing->id,
            ], 200);
        }

        $session = GameSession::create([
            'anon_token_id' => $anon?->id,
            'char_name' => $char,
            'app_version' => $request->input('app_version'),
            'started_at' => $startedAt,
            'ended_at' => Carbon::parse($request->input('ended_at')),
            'xp' => (int) $request->input('xp', 0),
            'sp' => (int) $request->input('sp', 0),
            'adena' => (int) $request->input('adena', 0),
            'mobs_killed' => (int) $request->input('mobs_killed', 0),
            'deaths' => (int) $request->input('deaths', 0),
            'level_ups' => (int) $request->input('level_ups', 0),
            'xp_per_hour' => (int) round((float) $request->input('xp_per_hour', 0)),
            'adena_per_hour' => (int) round((float) $request->input('adena_per_hour', 0)),
            'items_summary_json' => $request->input('items_summary'),
        ]);

        return response()->json([
            'status' => 'accepted',
            'session_id' => $session->id,
        ], 201);
    }

    public function byChar(Request $request, string $name): JsonResponse
    {
        $limit = min(self::MAX_PAGE_SIZE, max(1, (int) $request->query('limit', 20)));
        $page = max(1, (int) $request->query('page', 1));

        $query = GameSession::query()
            ->where('char_name', $name)
            ->orderByDesc('ended_at')
            ->orderByDesc('id');

        $total = (clone $query)->count();

        $sessions = $query
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(fn (GameSession $s) => [
                'id' => $s->id,
                'started_at' => $s->started_at->toIso8601String(),
                'ended_at' => $s->ended_at->toIso8601String(),
                'duration_seconds' => $s->durationSeconds(),
                'xp' => $s->xp,
                'sp' => $s->sp,
                'adena' => $s->adena,
                'mobs_killed' => $s->mobs_killed,
                'deaths' => $s->deaths,
                'level_ups' => $s->level_ups,
                'xp_per_hour' => $s->xp_per_hour,
                'adena_per_hour' => $s->adena_per_hour,
                'items_summary' => $s->items_summary_json,
                'app_version' => $s->app_version,
            ]);

        return response()->json([
            'char' => $name,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'sessions' => $sessions,
        ]);
    }
}
