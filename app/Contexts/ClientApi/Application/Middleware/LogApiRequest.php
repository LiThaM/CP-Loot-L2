<?php

namespace App\Contexts\ClientApi\Application\Middleware;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        $anon = $request->attributes->get('anon_token');
        $anonHash = $anon instanceof AnonToken ? substr($anon->hashedToken(), 0, 12) : null;

        Log::channel('api_v1')->info('api.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'anon_token_hash' => $anonHash,
            'ip_hash' => substr(hash('sha256', $request->ip() ?? ''), 0, 12),
        ]);

        return $response;
    }
}
