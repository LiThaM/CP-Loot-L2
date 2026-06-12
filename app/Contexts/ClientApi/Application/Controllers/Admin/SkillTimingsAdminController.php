<?php

namespace App\Contexts\ClientApi\Application\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * POST /api/v1/admin/data/skill_timings
 *
 * Admin upload del skill_timings.json (lo que se scrapea de la wiki).
 * Body = JSON crudo con el mismo formato que data/skill_timings.json
 * del cliente: {skill_id: {level: {duration, reuse, mp_cost, ...}}}.
 * Sanctum admin con ability `release:upload` (mismo token que releases
 * y gps/routes). Bug I de bugsApi.
 */
class SkillTimingsAdminController extends Controller
{
    private const STORAGE_PATH = 'data/skill_timings.json';
    private const DISK = 'client_blobs';
    private const MAX_BYTES = 10_485_760; // 10 MB — el bundle actual ronda 1.9 MB pretty-printed

    public function store(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        if ($raw === '' || $raw === false) {
            return response()->json(['error' => 'empty_body'], 422);
        }
        if (strlen($raw) > self::MAX_BYTES) {
            return response()->json([
                'error' => 'payload_too_large',
                'max_bytes' => self::MAX_BYTES,
                'received_bytes' => strlen($raw),
            ], 413);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            return response()->json([
                'error' => 'invalid_json',
                'message' => 'Body must be a non-empty JSON object of {skill_id: {level: {...}}}.',
            ], 422);
        }
        foreach ($decoded as $skillId => $levels) {
            if (!is_array($levels)) {
                return response()->json([
                    'error' => 'invalid_field',
                    'field' => (string) $skillId,
                    'expected' => 'object of {level: {...}}',
                ], 422);
            }
        }

        // Re-encode compacto: el bundle viene pretty-printed (~1.9 MB) y el
        // cliente solo lo parsea — sin whitespace baja a menos de la mitad.
        // PRESERVE_ZERO_FRACTION: el scraper Python escribe floats (3.0) y
        // sin el flag PHP los degradaría a int en el re-encode.
        $normalized = json_encode(
            $decoded,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($normalized === false) {
            return response()->json(['error' => 'encode_failed'], 500);
        }

        Storage::disk(self::DISK)->put(self::STORAGE_PATH, $normalized);

        return response()->json([
            'ok' => true,
            'bytes' => strlen($normalized),
            'sha256' => hash('sha256', $normalized),
            'skills' => count($decoded),
        ], 200);
    }
}
