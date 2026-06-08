<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/v1/gps/routes
 *
 * Sirve el routes.json del módulo GPS (raidboss + ciudades) que consume
 * el cliente AdenaLedgerStats al abrir el overlay. Bug C de bugsApi.
 *
 * Storage: storage/app/client_blobs/gps/routes.json (vía disk
 * `client_blobs`, mismo que los release ZIPs — sigue a S3 en prod si
 * CLIENT_BLOBS_DISK=s3). 404 si todavía no se subió ninguno; el cliente
 * cae a su bundle local en ese caso.
 *
 * Conditional GET: ETag = sha256 del body. Si If-None-Match coincide,
 * 304 sin body — evita ~50KB de tráfico en cada arranque del cliente.
 */
class GpsRoutesController extends Controller
{
    private const STORAGE_PATH = 'gps/routes.json';
    private const DISK = 'client_blobs';

    public function show(Request $request): Response|JsonResponse
    {
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists(self::STORAGE_PATH)) {
            return response()->json([
                'error' => 'not_uploaded',
                'message' => 'GPS routes have not been uploaded yet. Use POST /api/v1/admin/gps/routes.',
            ], 404);
        }

        $body = $disk->get(self::STORAGE_PATH);
        if ($body === null) {
            return response()->json(['error' => 'read_failed'], 500);
        }

        $etag = '"'.hash('sha256', $body).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($body, 200)
            ->header('Content-Type', 'application/json')
            ->header('ETag', $etag)
            ->header('Last-Modified', gmdate('D, d M Y H:i:s', $disk->lastModified(self::STORAGE_PATH)).' GMT')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
