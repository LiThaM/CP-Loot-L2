<?php

namespace App\Http\Middleware;

use App\Contexts\System\Domain\Models\UserActivity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log for non-ajax GETs or any state-changing method
        $isStateChanging = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
        $isPageVisit = $request->method() === 'GET' && ! $request->expectsJson();

        if ($isStateChanging || $isPageVisit) {
            $path = $request->path();
            
            if ($this->shouldLog($path)) {
                try {
                    UserActivity::create([
                        'user_id' => $request->user()?->id,
                        'path' => '/' . $path,
                        'method' => $request->method(),
                        'ip' => $request->ip(),
                        'user_agent' => $request->header('User-Agent'),
                        'payload' => $this->filterPayload($request->all()),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    \Log::error("Analytics error: " . $e->getMessage());
                }
            }
        }

        return $response;
    }

    private function shouldLog(string $path): bool
    {
        $ignored = ['up', 'api/health', 'broadcasting/auth', 'sanctum/csrf-cookie', 'build/*', 'storage/*', 'vendor/*', 'horizon/*'];
        foreach ($ignored as $pattern) {
            if ($path === $pattern || \Illuminate\Support\Str::is($pattern, $path)) {
                return false;
            }
        }
        return true;
    }

    private function filterPayload(array $payload): array
    {
        $sensitive = ['password', 'password_confirmation', 'current_password', 'token', 'invite_code'];
        foreach ($sensitive as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = '[FILTERED]';
            }
        }
        return $payload;
    }
}
