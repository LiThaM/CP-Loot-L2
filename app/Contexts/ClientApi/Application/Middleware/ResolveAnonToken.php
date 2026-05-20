<?php

namespace App\Contexts\ClientApi\Application\Middleware;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAnonToken
{
    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $raw = $request->header('X-Anon-Token');

        if (!$raw) {
            if ($mode === 'optional') {
                $request->attributes->set('anon_token', null);
                return $next($request);
            }

            return response()->json([
                'error' => 'missing_anon_token',
                'message' => 'Header X-Anon-Token is required.',
            ], 400);
        }

        if (!preg_match(self::UUID_REGEX, $raw)) {
            return response()->json([
                'error' => 'invalid_anon_token',
                'message' => 'X-Anon-Token must be a valid UUID v4.',
            ], 400);
        }

        $token = AnonToken::firstOrCreate(
            ['token_uuid' => strtolower($raw)],
            ['first_seen_at' => now(), 'last_seen_at' => now()]
        );

        if ($token->isBanned()) {
            return response()->json([
                'error' => 'token_banned',
                'message' => 'This anon token has been banned.',
            ], 423);
        }

        $token->forceFill([
            'last_seen_at' => now(),
            'request_count' => $token->request_count + 1,
        ])->save();

        $request->attributes->set('anon_token', $token);

        return $next($request);
    }
}
