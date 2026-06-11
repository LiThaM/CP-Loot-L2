<?php

namespace App\Contexts\Telemetry\Application\Controllers\Admin;

use App\Contexts\Telemetry\Application\Controllers\CalibrationFailuresController;
use App\Contexts\Telemetry\Domain\Models\CalibrationFailure;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin de reportes de calibración (bug D de bugsApi/BUGS.md):
 *
 *   GET /api/v1/admin/calibration/failures?kind=runtime_zero_readings
 *   GET /api/v1/admin/calibration/failures/{id}/image
 *
 * El listado devuelve el meta completo (readings, calibration,
 * game_size, screen_size...) para cruzar varios usuarios y confirmar el
 * desajuste tamaño/DPI; el frame PNG se ve con el endpoint /image.
 * Filtros: ?kind=, ?version=, ?char=, ?limit= (50 por defecto, máx 200).
 */
class CalibrationFailuresAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        $query = CalibrationFailure::query()->orderByDesc('id');

        if ($request->filled('kind')) {
            $query->where('kind', $request->query('kind'));
        }
        if ($request->filled('version')) {
            $query->where('app_version', $request->query('version'));
        }
        if ($request->filled('char')) {
            $query->where('char_name', $request->query('char'));
        }

        $total = (clone $query)->count();

        $failures = $query->limit($limit)->get()->map(fn (CalibrationFailure $f) => [
            'id' => $f->id,
            'kind' => $f->kind,
            'char' => $f->char_name,
            'app_version' => $f->app_version,
            'meta' => $f->meta_json,
            'image_bytes' => $f->image_bytes,
            'image_url' => $f->image_path !== null
                ? url("/api/v1/admin/calibration/failures/{$f->id}/image")
                : null,
            'created_at' => $f->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'total' => $total,
            'count' => $failures->count(),
            'failures' => $failures,
        ]);
    }

    public function image(int $id): Response|JsonResponse
    {
        $failure = CalibrationFailure::find($id);
        if ($failure === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $disk = Storage::disk(CalibrationFailuresController::DISK);
        if ($failure->image_path === null || !$disk->exists($failure->image_path)) {
            return response()->json(['error' => 'no_image'], 404);
        }

        $path = $failure->image_path;

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => $disk->size($path),
            'Content-Disposition' => 'inline; filename="failure_'.$failure->id.'.png"',
        ]);
    }
}
