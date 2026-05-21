<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Nueva página `/system/cps` para admins:**

- Listado completo de CPs con buscador, filtros (crónica, activas/inactivas/sin miembros) y KPIs por fila: miembros, fondo CP en adena, reportes confirmados y última actividad.
- Editar nombre, servidor y crónica de cualquier CP desde un modal.
- Acciones rápidas por fila: ver detalle, impersonar al líder, activar/desactivar, eliminar (si está vacía).
- Panel de solicitudes pendientes de CP integrado arriba, con aprobar/rechazar en línea.
- Reemplaza al widget del dashboard cuando necesitas más de un vistazo rápido.
MD;

        $bodyEn = <<<'MD'
**New `/system/cps` admin page:**

- Full CP roster with search, filters (chronicle, active/inactive/empty) and per-row KPIs: members, CP-fund adena, confirmed reports, last activity.
- Edit any CP's name, server and chronicle from a modal.
- Per-row quick actions: view detail, impersonate leader, toggle active, delete (if empty).
- Pending CP requests panel built in at the top, with inline approve/reject.
- Replaces the dashboard widget when you need more than a quick glance.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Admin: full CPs roster page'],
            [
                'audience' => 'web',
                'title_es' => 'Admin: página completa de CPs',
                'title_en' => 'Admin: full CPs roster page',
                'body_es' => $bodyEs,
                'body_en' => $bodyEn,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_en', 'Admin: full CPs roster page')
            ->delete();
    }
};
