<?php

namespace App\Contexts\ClientApi\Application\Controllers\Admin;

use App\Contexts\ClientApi\Domain\Models\VersionDownload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/admin/version/adoption — conteo de installs que descargaron
 * cada versión (bug H de bugsApi/BUGS.md). Sanctum admin.
 *
 * `installs` cuenta anon_tokens distintos; `downloads` cuenta filas (las
 * sin anon_token cuentan como download pero no como install). Filtro
 * opcional ?version=. Orden: actividad más reciente primero.
 */
class VersionAdoptionAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VersionDownload::query()
            ->select([
                'to_version',
                DB::raw('count(*) as downloads'),
                DB::raw('count(distinct anon_token_id) as installs'),
                DB::raw('max(created_at) as last_download_at'),
            ])
            ->groupBy('to_version')
            ->orderByDesc(DB::raw('max(created_at)'));

        if ($request->filled('version')) {
            $query->where('to_version', $request->query('version'));
        }

        $versions = $query->get()->map(fn ($row) => [
            'to_version' => $row->to_version,
            'installs' => (int) $row->installs,
            'downloads' => (int) $row->downloads,
            'last_download_at' => $row->last_download_at,
        ]);

        return response()->json([
            'total_versions' => $versions->count(),
            'versions' => $versions,
        ]);
    }
}
