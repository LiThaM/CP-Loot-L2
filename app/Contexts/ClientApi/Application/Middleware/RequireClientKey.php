<?php

namespace App\Contexts\ClientApi\Application\Middleware;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireClientKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->header('X-Client-Key');

        if (!$raw || !is_string($raw) || strlen($raw) < 16 || strlen($raw) > 200) {
            return response()->json([
                'error' => 'missing_client_key',
                'message' => 'Header X-Client-Key is required for this endpoint.',
            ], 401);
        }

        $key = ClientApiKey::findByRawKey($raw);

        if (!$key || !$key->isUsable()) {
            return response()->json([
                'error' => 'invalid_client_key',
                'message' => 'The provided X-Client-Key is invalid, expired, or revoked.',
            ], 401);
        }

        $key->forceFill([
            'last_used_at' => now(),
            'use_count' => $key->use_count + 1,
        ])->save();

        $request->attributes->set('client_api_key', $key);

        return $next($request);
    }
}
