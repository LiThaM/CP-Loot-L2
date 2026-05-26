<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Fix en Craft Bulk:** la lista de sub-crafts duplicaba el mismo material (p.ej. *Steel × 497* y *Steel × 21 378* como dos filas distintas) cuando varias recetas en la planificación lo demandaban desde ramas separadas. Ahora se agrupa en una sola fila con la suma total. Las cifras del bloque "Totales" no estaban afectadas — solo la sección de sub-crafts.
MD;

        $bodyEn = <<<'MD'
**Craft Bulk fix:** the sub-crafts list used to duplicate the same material (e.g. *Steel × 497* and *Steel × 21,378* as two separate rows) when several recipes in the plan demanded it from different branches. Now it collapses into a single row with the combined total. The "Totals" section was already correct — only the sub-crafts list was affected.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'fix', 'version' => null, 'title_en' => 'Craft Bulk: dedupe sub-crafts of the same item'],
            [
                'audience' => 'web',
                'title_es' => 'Craft Bulk: dedupe de sub-crafts del mismo item',
                'title_en' => 'Craft Bulk: dedupe sub-crafts of the same item',
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
            ->where('title_en', 'Craft Bulk: dedupe sub-crafts of the same item')
            ->delete();
    }
};
