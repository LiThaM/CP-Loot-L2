<?php

namespace App\Contexts\Telemetry\Application\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeoIpService
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            env('GEOIP_PROVIDER') ?: null,
            env('GEOIP_API_KEY') ?: null,
        );
    }

    public function lookup(?string $ip): ?string
    {
        if (!$ip || $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '10.')) {
            return null;
        }

        if (!$this->provider) {
            return null;
        }

        $cacheKey = 'geoip:'.hash('sha256', $ip);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($ip) {
            try {
                return match ($this->provider) {
                    'ipapi' => $this->ipapiLookup($ip),
                    default => null,
                };
            } catch (Throwable $e) {
                Log::channel('api_v1')->warning('geoip.lookup_failed', [
                    'provider' => $this->provider,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    private function ipapiLookup(string $ip): ?string
    {
        $response = Http::timeout(2)->get("https://ipapi.co/{$ip}/country/");
        if (!$response->ok()) {
            return null;
        }
        $body = trim($response->body());
        if (preg_match('/^[A-Z]{2}$/', $body)) {
            return $body;
        }
        return null;
    }
}
