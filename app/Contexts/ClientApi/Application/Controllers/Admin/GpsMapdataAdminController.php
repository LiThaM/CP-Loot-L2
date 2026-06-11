<?php

namespace App\Contexts\ClientApi\Application\Controllers\Admin;

use App\Contexts\ClientApi\Application\Controllers\GpsMapdataController;
use App\Contexts\ClientApi\Domain\Models\GpsMapdataVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Admin del paquete comunitario GPS (bug E de bugsApi/BUGS.md):
 *
 *   GET  /api/v1/admin/gps/mapdata/versions     — historial (últimas 10)
 *   POST /api/v1/admin/gps/mapdata/revert/{id}  — restaurar una versión
 *
 * Para cuando un cliente sube basura: se lista el historial, se elige la
 * última versión sana y se revierte. El revert crea una versión NUEVA
 * (source=revert) apuntando al contenido restaurado, así la fila más
 * reciente sigue reflejando siempre el blob actual (invariante del ETag
 * del GET público). Sanctum + ability release:upload (mismo token que
 * releases).
 */
class GpsMapdataAdminController extends Controller
{
    public function versions(): JsonResponse
    {
        $latestId = GpsMapdataVersion::query()->max('id');

        $versions = GpsMapdataVersion::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (GpsMapdataVersion $v) => [
                'id' => $v->id,
                'sha256' => $v->sha256,
                'size_bytes' => $v->size_bytes,
                'source' => $v->source,
                'reverted_from_id' => $v->reverted_from_id,
                'anon_token_id' => $v->anon_token_id,
                'created_at' => $v->created_at?->toIso8601String(),
                'current' => $v->id === $latestId,
            ]);

        return response()->json(['versions' => $versions]);
    }

    public function revert(int $id): JsonResponse
    {
        $version = GpsMapdataVersion::find($id);
        if ($version === null) {
            return response()->json(['error' => 'version_not_found'], 404);
        }

        $disk = Storage::disk(GpsMapdataController::DISK);
        if ($version->storage_path === '' || !$disk->exists($version->storage_path)) {
            return response()->json(['error' => 'version_binary_missing'], 410);
        }

        $bytes = $disk->get($version->storage_path);
        if ($bytes === null) {
            return response()->json(['error' => 'read_failed'], 500);
        }

        $restored = GpsMapdataVersion::create([
            'anon_token_id' => null,
            'storage_path' => '',
            'sha256' => $version->sha256,
            'size_bytes' => strlen($bytes),
            'source' => 'revert',
            'reverted_from_id' => $version->id,
        ]);

        $restoredPath = GpsMapdataController::VERSIONS_DIR.'/'.$restored->id.'.npz';
        if (!$disk->put($restoredPath, $bytes) || !$disk->put(GpsMapdataController::CURRENT_PATH, $bytes)) {
            $restored->delete();
            return response()->json(['error' => 'storage_failed'], 500);
        }
        $restored->update(['storage_path' => $restoredPath]);

        GpsMapdataController::pruneVersions($disk);

        return response()->json([
            'ok' => true,
            'reverted_to' => $version->id,
            'new_version_id' => $restored->id,
            'sha256' => $restored->sha256,
            'bytes' => $restored->size_bytes,
        ]);
    }
}
