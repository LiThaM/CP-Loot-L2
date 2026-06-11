<?php

namespace App\Contexts\Telemetry\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Domain\Models\CalibrationFailure;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * POST /api/v1/calibration/failures — reportes de fallo de calibración
 * (bug D de bugsApi/BUGS.md). El cliente lleva desde 0.5.27 posteando
 * aquí best-effort (404 silencioso hasta ahora); este endpoint cierra
 * el circuito para poder diagnosticar la raíz del "0% sostenido"
 * (sospecha: desajuste tamaño/DPI calibrador vs WGC en runtime).
 *
 * Multipart: `meta` (JSON plano, form field) + `image` (PNG del frame,
 * opcional — el overlay puede no tener captura). Dos remitentes:
 *   - calibrador: meta sin `kind` (rect, game_size, screen_size...)
 *   - overlay 0%: meta.kind = "runtime_zero_readings" + readings,
 *     calibration (el calibration.json del user), tesseract, wgc, char.
 * El meta entero se persiste en meta_json; kind/char/app_version se
 * extraen a columnas para filtrar desde el admin.
 */
class CalibrationFailuresController extends Controller
{
    public const DISK = 'client_blobs';
    public const MAX_IMAGE_BYTES = 10_485_760; // 10 MB — frame full-HD PNG ronda 2-4 MB
    public const MAX_META_BYTES = 262_144;     // 256 KB — meta lleva el calibration.json entero

    public function store(Request $request): JsonResponse
    {
        $rawMeta = $request->input('meta');
        if (!is_string($rawMeta) || $rawMeta === '') {
            return response()->json([
                'error' => 'missing_meta',
                'message' => 'Multipart form field "meta" (JSON) is required.',
            ], 422);
        }
        if (strlen($rawMeta) > self::MAX_META_BYTES) {
            return response()->json(['error' => 'meta_too_large'], 413);
        }

        $meta = json_decode($rawMeta, true);
        if (!is_array($meta)) {
            return response()->json(['error' => 'invalid_meta_json'], 422);
        }

        $imageBytes = null;
        $image = $request->file('image');
        if ($image !== null) {
            if (!$image->isValid()) {
                return response()->json(['error' => 'invalid_image_upload'], 422);
            }
            if ($image->getSize() > self::MAX_IMAGE_BYTES) {
                return response()->json([
                    'error' => 'image_too_large',
                    'max_bytes' => self::MAX_IMAGE_BYTES,
                ], 413);
            }
            $imageBytes = file_get_contents($image->getRealPath());
            if ($imageBytes === false || $imageBytes === '') {
                return response()->json(['error' => 'cannot_read_image'], 422);
            }
            // Magic bytes — mismo guard que /ocr/samples. Sin límite de
            // dimensiones: aquí el full frame es justo lo que necesitamos.
            if (substr($imageBytes, 0, 8) !== "\x89PNG\r\n\x1A\n") {
                return response()->json(['error' => 'invalid_png'], 422);
            }
        }

        /** @var AnonToken|null $anon */
        $anon = $request->attributes->get('anon_token');

        $failure = CalibrationFailure::create([
            'anon_token_id' => $anon?->id,
            'kind' => is_string($meta['kind'] ?? null) ? substr($meta['kind'], 0, 50) : 'calibrator',
            'char_name' => is_string($meta['char'] ?? null) ? substr($meta['char'], 0, 100) : null,
            'app_version' => is_string($meta['app_version'] ?? null) ? substr($meta['app_version'], 0, 50) : null,
            'meta_json' => $meta,
        ]);

        if ($imageBytes !== null) {
            $path = sprintf('calibration/failures/%d.png', $failure->id);
            if (Storage::disk(self::DISK)->put($path, $imageBytes)) {
                $failure->update([
                    'image_path' => $path,
                    'image_bytes' => strlen($imageBytes),
                ]);
            }
        }

        return response()->json([
            'status' => 'accepted',
            'failure_id' => $failure->id,
        ], 201);
    }
}
