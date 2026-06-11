<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Contexts\Telemetry\Application\Services\GeoIpService::class,
            fn () => \App\Contexts\Telemetry\Application\Services\GeoIpService::fromConfig()
        );
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        JsonResource::withoutWrapping();

        $this->configureApiRateLimiters();
    }

    private function configureApiRateLimiters(): void
    {
        RateLimiter::for('api-v1', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('api-v1-upload', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            $byAnon = $anon ? Limit::perMinute(1)->by('anon:'.$anon->id) : null;
            $byIp = Limit::perMinute(5)->by($request->ip());

            return $byAnon ? [$byAnon, $byIp] : [$byIp];
        });

        RateLimiter::for('api-v1-telemetry', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            return $anon
                ? Limit::perDay(20)->by('anon:'.$anon->id)
                : Limit::perDay(20)->by($request->ip());
        });

        // /ocr/samples es público (sin client_key). Defensa en profundidad:
        //   1. 200/día/anon — tope nominal por install
        //   2. 30/hora/anon — frena bursts del mismo install
        //   3. 60/min/IP    — mitiga IPs rotando anon_tokens
        // Si por alguna razón el middleware anon_token no resolvió token
        // (no debería pasar en producción), caemos a un solo limit por IP.
        RateLimiter::for('api-v1-ocr-samples', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            $ipLimit = Limit::perMinute(60)->by($request->ip());

            if ($anon === null) {
                return [$ipLimit];
            }

            return [
                Limit::perDay(200)->by('anon:'.$anon->id),
                Limit::perHour(30)->by('anon:'.$anon->id),
                $ipLimit,
            ];
        });

        RateLimiter::for('api-v1-crashes', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            return $anon
                ? Limit::perMinute(10)->by('anon:'.$anon->id)
                : Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api-v1-tickets', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            return $anon
                ? Limit::perHour(5)->by('anon:'.$anon->id)
                : Limit::perHour(5)->by($request->ip());
        });

        // POST /gps/mapdata — el cliente sólo sube si creció ≥20 huellas
        // y con cooldown de 10 min, así que 6/hora por install sobra
        // (bugsApi bug E). Cap extra por IP contra anon_tokens rotados.
        RateLimiter::for('api-v1-gps-mapdata', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            $byIp = Limit::perHour(12)->by($request->ip());
            return $anon
                ? [Limit::perHour(6)->by('anon:'.$anon->id), $byIp]
                : [$byIp];
        });

        // POST /app/crashes — 10/hora por install (bugsApi bug F). Los
        // crashes repetidos igualmente dedupean por fingerprint.
        RateLimiter::for('api-v1-app-crashes', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            return $anon
                ? Limit::perHour(10)->by('anon:'.$anon->id)
                : Limit::perHour(10)->by($request->ip());
        });

        // POST /sessions — una sesión de farmeo dura horas; 30/hora cubre
        // de sobra el flush del outbox al arrancar (bugsApi bug G).
        RateLimiter::for('api-v1-sessions', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            return $anon
                ? Limit::perHour(30)->by('anon:'.$anon->id)
                : Limit::perHour(30)->by($request->ip());
        });
    }
}
