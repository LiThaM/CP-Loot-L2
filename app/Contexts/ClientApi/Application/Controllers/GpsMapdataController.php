<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\ClientApi\Domain\Models\GpsMapdataVersion;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET/POST /api/v1/gps/mapdata — conocimiento comunitario del mapa
 * (bug E de bugsApi/BUGS.md). El cliente aprende huellas del minimapa +
 * calibraciones de ciudad (core/gps_community.py) y lo que aprende uno
 * lo heredan todos, como las plantillas de dígitos.
 *
 * GET: binario npz del paquete comunitario. Los clientes lo piden EN
 * CADA ARRANQUE y puede llegar a ~27 MB → conditional GET obligatorio:
 * ETag = sha256 (de la fila más reciente de gps_mapdata_versions, sin
 * leer el blob) + Last-Modified; If-None-Match → 304 sin body. El body
 * se sirve por stream para no cargar 27 MB en RAM por request.
 *
 * POST: multipart field `mapdata` (gps_mapdata.npz). Last-writer-wins a
 * propósito: el cliente SIEMPRE descarga y funde el paquete remoto ANTES
 * de subir, así el paquete solo crece y dos clientes concurrentes
 * convergen. Cada subida deja copia versionada (últimas 10) para poder
 * revertir basura via /admin/gps/mapdata/revert/{id}.
 */
class GpsMapdataController extends Controller
{
    public const CURRENT_PATH = 'gps/mapdata.npz';
    public const VERSIONS_DIR = 'gps/mapdata_versions';
    public const DISK = 'client_blobs';
    public const MAX_BYTES = 31_457_280; // 30 MB (tope cliente ~27 MB)
    public const KEEP_VERSIONS = 10;

    public function show(Request $request): Response|JsonResponse
    {
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists(self::CURRENT_PATH)) {
            return response()->json([
                'error' => 'not_uploaded',
                'message' => 'No community mapdata has been uploaded yet.',
            ], 404);
        }

        // sha256 desde la fila más reciente — la última versión SIEMPRE
        // refleja el blob actual (upload y revert crean fila). Fallback a
        // hashear el archivo si alguien lo dejó en storage a mano.
        $latest = GpsMapdataVersion::query()->latest('id')->first();
        $sha = $latest?->sha256 ?? hash('sha256', (string) $disk->get(self::CURRENT_PATH));
        $etag = '"'.$sha.'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $disk->size(self::CURRENT_PATH),
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $disk->lastModified(self::CURRENT_PATH)).' GMT',
            'Cache-Control' => 'public, max-age=300',
        ];

        return new StreamedResponse(function () use ($disk) {
            $stream = $disk->readStream(self::CURRENT_PATH);
            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    public function store(Request $request): JsonResponse
    {
        $file = $request->file('mapdata');
        if ($file === null || !$file->isValid()) {
            return response()->json([
                'error' => 'missing_file',
                'message' => 'Multipart file field "mapdata" is required.',
            ], 422);
        }

        // 413 explícito (la spec del cliente lo espera) antes de leer nada.
        $size = $file->getSize();
        if ($size === false || $size > self::MAX_BYTES) {
            return response()->json([
                'error' => 'payload_too_large',
                'max_bytes' => self::MAX_BYTES,
                'received_bytes' => (int) $size,
            ], 413);
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') {
            return response()->json(['error' => 'cannot_read_file'], 422);
        }

        // npz = ZIP — magic bytes "PK\x03\x04". Frena uploads de basura
        // que ni siquiera son el formato esperado.
        if (substr($bytes, 0, 4) !== "PK\x03\x04") {
            return response()->json(['error' => 'invalid_npz'], 422);
        }

        $sha = hash('sha256', $bytes);

        /** @var AnonToken|null $anon */
        $anon = $request->attributes->get('anon_token');

        // No-op si es byte-idéntico a lo actual: no quemar un slot de
        // versión ni reescribir 27 MB para nada.
        $latest = GpsMapdataVersion::query()->latest('id')->first();
        if ($latest !== null && $latest->sha256 === $sha) {
            return response()->json([
                'ok' => true,
                'unchanged' => true,
                'sha256' => $sha,
                'bytes' => strlen($bytes),
                'version_id' => $latest->id,
            ], 200);
        }

        $disk = Storage::disk(self::DISK);

        $version = GpsMapdataVersion::create([
            'anon_token_id' => $anon?->id,
            'storage_path' => '',
            'sha256' => $sha,
            'size_bytes' => strlen($bytes),
            'source' => 'upload',
        ]);

        $versionPath = self::VERSIONS_DIR.'/'.$version->id.'.npz';
        if (!$disk->put($versionPath, $bytes) || !$disk->put(self::CURRENT_PATH, $bytes)) {
            $version->delete();
            return response()->json(['error' => 'storage_failed'], 500);
        }
        $version->update(['storage_path' => $versionPath]);

        self::pruneVersions($disk);

        return response()->json([
            'ok' => true,
            'sha256' => $sha,
            'bytes' => strlen($bytes),
            'version_id' => $version->id,
        ], 201);
    }

    public static function pruneVersions(Filesystem $disk): void
    {
        $stale = GpsMapdataVersion::query()
            ->orderByDesc('id')
            ->skip(self::KEEP_VERSIONS)
            ->take(100)
            ->get();

        foreach ($stale as $old) {
            if ($old->storage_path !== '' && $disk->exists($old->storage_path)) {
                $disk->delete($old->storage_path);
            }
            $old->delete();
        }
    }
}
