<?php

namespace App\Contexts\ClientApi\Application\Controllers\Admin;

use App\Contexts\ClientApi\Domain\Models\CrashReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/admin/app/crashes — listado de crashes del cliente
 * AdenaLedgerStats (bug F de bugsApi/BUGS.md). Sanctum admin.
 *
 * Filtros: ?version=0.5.31-alpha, ?fingerprint=..., ?limit= (50 por
 * defecto, máx 200). Solo filas con app_version (las legacy del bot,
 * que llevan bot_version, no salen aquí). Orden: último visto primero.
 */
class AppCrashesAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        $query = CrashReport::query()
            ->whereNotNull('app_version')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id');

        if ($request->filled('version')) {
            $query->where('app_version', $request->query('version'));
        }
        if ($request->filled('fingerprint')) {
            $query->where('fingerprint', $request->query('fingerprint'));
        }

        $total = (clone $query)->count();

        $crashes = $query->limit($limit)->get()->map(fn (CrashReport $c) => [
            'id' => $c->id,
            'app_version' => $c->app_version,
            'char' => $c->char_name,
            'os_version' => $c->os_version,
            'fingerprint' => $c->fingerprint,
            'occurrences' => $c->occurrences,
            'traceback' => $c->stack_trace,
            'context' => $c->context_json,
            'client_ts' => $c->client_ts?->toIso8601String(),
            'first_seen_at' => $c->reported_at?->toIso8601String(),
            'last_seen_at' => $c->last_seen_at?->toIso8601String(),
        ]);

        return response()->json([
            'total' => $total,
            'count' => $crashes->count(),
            'crashes' => $crashes,
        ]);
    }
}
