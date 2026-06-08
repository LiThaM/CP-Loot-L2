<?php

namespace App\Contexts\ClientApi\Application\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * POST /api/v1/admin/gps/routes
 *
 * Admin upload del routes.json del módulo GPS. Recibe el JSON crudo en
 * el body (Content-Type: application/json) y lo persiste en
 * client_blobs/gps/routes.json. Sanctum admin con ability
 * `release:upload` (reuso del token existente — si en algún momento
 * quieres separar scopes, cambia a 'gps:upload' y emite token nuevo).
 *
 * Valida estructura mínima: claves top-level `version`, `generated_at`,
 * `data` (obj con raids). El resto del payload pasa intacto.
 */
class GpsRoutesAdminController extends Controller
{
    private const STORAGE_PATH = 'gps/routes.json';
    private const DISK = 'client_blobs';
    private const MAX_BYTES = 5_242_880; // 5 MB — el routes.json actual ronda 50 KB

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
        if (!is_array($decoded)) {
            return response()->json([
                'error' => 'invalid_json',
                'message' => 'Body must be a valid JSON object.',
            ], 422);
        }
        foreach (['version', 'generated_at', 'data'] as $required) {
            if (!array_key_exists($required, $decoded)) {
                return response()->json([
                    'error' => 'missing_field',
                    'field' => $required,
                ], 422);
            }
        }
        if (!is_array($decoded['data'])) {
            return response()->json([
                'error' => 'invalid_field',
                'field' => 'data',
                'expected' => 'object',
            ], 422);
        }

        // Re-encode para normalizar (sin pretty-print — el cliente solo lo parsea)
        // y evitar persistir whitespace inflado.
        $normalized = json_encode(
            $decoded,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if ($normalized === false) {
            return response()->json(['error' => 'encode_failed'], 500);
        }

        Storage::disk(self::DISK)->put(self::STORAGE_PATH, $normalized);

        return response()->json([
            'ok' => true,
            'version' => $decoded['version'],
            'generated_at' => $decoded['generated_at'],
            'bytes' => strlen($normalized),
            'sha256' => hash('sha256', $normalized),
            'raids' => count($decoded['data']),
        ], 200);
    }
}
