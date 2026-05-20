<?php

namespace App\Contexts\Telemetry\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Application\Requests\SubmitTelemetrySessionRequest;
use App\Contexts\Telemetry\Application\Services\GeoIpService;
use App\Contexts\Telemetry\Domain\Models\TelemetrySession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TelemetrySessionController extends Controller
{
    public function __construct(private readonly GeoIpService $geoIp) {}

    public function store(SubmitTelemetrySessionRequest $request): JsonResponse
    {
        /** @var AnonToken $anon */
        $anon = $request->attributes->get('anon_token');

        $countryCode = $this->geoIp->lookup($request->ip());
        if ($countryCode) {
            $anon->forceFill(['country_code_last' => $countryCode])->save();
        }

        $session = TelemetrySession::create([
            'anon_token_id' => $anon->id,
            'country_code' => $countryCode,
            'bot_version' => $request->input('bot_version'),
            'os_version' => $request->input('os_version'),
            'python_version' => $request->input('python_version'),
            'session_duration_seconds' => (int) $request->input('session_duration_seconds', 0),
            'char_class' => $request->input('char_class'),
            'char_level' => $request->input('char_level'),
            'xp_per_hour' => (int) $request->input('xp_per_hour', 0),
            'adena_per_hour' => (int) $request->input('adena_per_hour', 0),
            'ss_per_hour' => (int) $request->input('ss_per_hour', 0),
            'deaths' => (int) $request->input('deaths', 0),
            'level_ups' => (int) $request->input('level_ups', 0),
            'top_items_json' => $request->input('top_items'),
            'ocr_engine' => $request->input('ocr_engine'),
            'ocr_avg_ms' => $request->input('ocr_avg_ms'),
            'ocr_p95_ms' => $request->input('ocr_p95_ms'),
            'ocr_errors' => $request->input('ocr_errors'),
            'ocr_gpu_used' => $request->input('ocr_gpu_used'),
        ]);

        return response()->json([
            'status' => 'accepted',
            'session_id' => $session->id,
            'country_code' => $countryCode,
        ], 201);
    }
}
