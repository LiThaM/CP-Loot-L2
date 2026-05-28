<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Pase de salud interna sin features nuevas — tres frentes:

- **Seguridad:** se han cerrado dos hueco de mass-assignment (un usuario podía promocionarse a admin vía un POST malicioso; un líder podía reasignar la CP) y se han eliminado rutas Discord huérfanas que rompían `php artisan route:list`.
- **Rendimiento:** la pestaña History de [/loot](/loot) ya no dispara una ráfaga de queries por report. Tres consultas N+1 (recipients, item Adena, points logs) se han batched en una sola y se han añadido índices compuestos en `loot_reports` para CPs con histórico grande.
- **Mantenibilidad:** los formatters de adena y los helpers de loot (event icons, status colors, entry amounts) viven en `resources/js/utils/{adena,loot}.js` en vez de duplicados en cuatro pantallas.
MD;

        $bodyEn = <<<'MD'
Internal health pass with no new features — three fronts:

- **Security:** closed two mass-assignment holes (a user could promote themselves to admin via a crafted POST; a CP leader could reassign the party) and removed orphan Discord OAuth routes that crashed `php artisan route:list`.
- **Performance:** the History tab of [/loot](/loot) no longer fires a query burst per report. Three N+1 calls (recipients, Adena item, points logs) collapsed into one and `loot_reports` gained composite indexes for CPs with deep history.
- **Maintainability:** adena formatters and loot helpers (event icons, status colors, entry amounts) now live in `resources/js/utils/{adena,loot}.js` instead of duplicated across four screens.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'chore', 'version' => null, 'title_en' => 'Health sprint: security, performance, dedupe'],
            [
                'audience' => 'web',
                'title_es' => 'Pase de salud: seguridad, rendimiento, deduplicación',
                'title_en' => 'Health sprint: security, performance, dedupe',
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
            ->where('title_en', 'Health sprint: security, performance, dedupe')
            ->delete();
    }
};
