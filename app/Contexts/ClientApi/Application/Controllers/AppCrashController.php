<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Application\Requests\SubmitAppCrashRequest;
use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\ClientApi\Domain\Models\CrashReport;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/app/crashes — crash reports genéricos del cliente
 * AdenaLedgerStats (bug F de bugsApi/BUGS.md). Regla del proyecto: "si
 * la app ve errores los sube, no podemos estar a ciegas".
 *
 * Reusa la tabla crash_reports (los legacy del bot llevan bot_version;
 * estos llevan app_version). Dedup server-side: mismo fingerprint del
 * traceback (paths/líneas normalizados) + misma app_version → se
 * incrementa `occurrences` en vez de crear filas repetidas. El dedup es
 * por versión a propósito: si un crash sigue apareciendo tras un fix,
 * sale como fila nueva de la versión nueva.
 */
class AppCrashController extends Controller
{
    public function store(SubmitAppCrashRequest $request): JsonResponse
    {
        /** @var AnonToken|null $anon */
        $anon = $request->attributes->get('anon_token');

        $traceback = (string) $request->input('traceback');
        $fingerprint = CrashReport::buildFingerprint($traceback);

        $existing = CrashReport::query()
            ->where('fingerprint', $fingerprint)
            ->where('app_version', $request->input('app_version'))
            ->first();

        if ($existing !== null) {
            $existing->increment('occurrences');
            $existing->forceFill([
                'last_seen_at' => now(),
                'char_name' => $request->input('char') ?? $existing->char_name,
            ])->save();

            return response()->json([
                'status' => 'deduplicated',
                'crash_id' => $existing->id,
                'fingerprint' => $fingerprint,
                'occurrences' => $existing->occurrences,
            ], 200);
        }

        $report = CrashReport::create([
            'anon_token_id' => $anon?->id,
            'app_version' => $request->input('app_version'),
            'char_name' => $request->input('char'),
            'os_version' => $request->input('os_version'),
            'fingerprint' => $fingerprint,
            'stack_trace' => $traceback,
            'context_json' => $request->input('context'),
            'occurrences' => 1,
            'reported_at' => now(),
            'last_seen_at' => now(),
            'client_ts' => $this->parseClientTs($request->input('ts')),
        ]);

        return response()->json([
            'status' => 'accepted',
            'crash_id' => $report->id,
            'fingerprint' => $fingerprint,
            'occurrences' => 1,
        ], 201);
    }

    /**
     * El cliente manda `ts` como epoch (time.time()) pero aceptamos
     * también ISO-8601 — y si no parsea, null antes que romper el report.
     */
    private function parseClientTs(mixed $ts): ?Carbon
    {
        if ($ts === null || $ts === '') {
            return null;
        }
        try {
            if (is_numeric($ts)) {
                return Carbon::createFromTimestamp((float) $ts);
            }
            return Carbon::parse((string) $ts);
        } catch (\Throwable) {
            return null;
        }
    }
}
