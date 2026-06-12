<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/v1/data/skill_timings
 *
 * Timings comunitarios de buffs/skills (duration, reuse, mp_cost por
 * skill_id+level) que el cliente AdenaLedgerStats baja al arrancar el
 * overlay y cachea con prioridad sobre su bundle. Bug I de bugsApi:
 * permite que todos los clientes hereden timings nuevos scrapeados de
 * la wiki sin esperar a un release.
 *
 * Mismo patrón que /gps/routes (bug C): blob en
 * client_blobs/data/skill_timings.json, ETag = sha256 del body, 304 con
 * If-None-Match (el cliente lo manda), 404 not_uploaded si aún no se
 * subió nada — el cliente cae a su bundle local sin romper.
 */
class SkillTimingsController extends Controller
{
    private const STORAGE_PATH = 'data/skill_timings.json';
    private const DISK = 'client_blobs';

    public function show(Request $request): Response|JsonResponse
    {
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists(self::STORAGE_PATH)) {
            return response()->json([
                'error' => 'not_uploaded',
                'message' => 'Skill timings have not been uploaded yet. Use POST /api/v1/admin/data/skill_timings.',
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
