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

        RateLimiter::for('api-v1-ocr-samples', function (Request $request) {
            $anon = $request->attributes->get('anon_token');
            return $anon
                ? Limit::perDay(200)->by('anon:'.$anon->id)
                : Limit::perDay(200)->by($request->ip());
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
    }
}
