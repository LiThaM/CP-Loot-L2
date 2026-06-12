<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Application\Requests\SubmitVersionDownloadedRequest;
use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\ClientApi\Domain\Models\VersionDownload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/version/downloaded — telemetría de adopción de updates
 * (bug H de bugsApi/BUGS.md). El cliente (core/updater.py) avisa justo
 * tras descargar+verificar el ZIP y antes del swap, best-effort con
 * timeout de 5s: aquí sólo registramos y respondemos rápido.
 *
 * Idempotente por (install, versión destino): un retry no infla los
 * conteos. Si llega sin anon_token resuelto se registra igual (fila con
 * anon null) — mejor un dato impreciso que perderlo.
 */
class VersionDownloadedController extends Controller
{
    public function store(SubmitVersionDownloadedRequest $request): JsonResponse
    {
        /** @var AnonToken|null $anon */
        $anon = $request->attributes->get('anon_token');

        $toVersion = (string) $request->input('to_version');

        if ($anon !== null) {
            $existing = VersionDownload::query()
                ->where('anon_token_id', $anon->id)
                ->where('to_version', $toVersion)
                ->first();

            if ($existing !== null) {
                return response()->json(['status' => 'duplicate'], 200);
            }
        }

        VersionDownload::create([
            'anon_token_id' => $anon?->id,
            'from_version' => $request->input('from_version'),
            'to_version' => $toVersion,
        ]);

        return response()->json(['status' => 'accepted'], 201);
    }
}
