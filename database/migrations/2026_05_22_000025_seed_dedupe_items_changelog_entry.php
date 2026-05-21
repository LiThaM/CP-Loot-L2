<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Fix:** algunos items aparecían duplicados en los listados (p.ej. *Recipe: Sealed Avadon Gloves (60%)* salía dos veces, una con categoría `Recipe` y otra como `EtcItem`). Se ha unificado: solo se muestra la fila canónica, y las referencias en farm reports, recetas y wishlists se han migrado automáticamente al id correcto. Las filas duplicadas no se borran — se marcan ocultas para que el histórico siga siendo trazable.
MD;

        $bodyEn = <<<'MD'
**Fix:** some items appeared twice in pickers (e.g. *Recipe: Sealed Avadon Gloves (60%)* showed up as both `Recipe` and `EtcItem`). The canonical row is now the only one visible, and existing references in farm reports, recipes and wishlists were migrated to it. Duplicates aren't deleted — they're flagged hidden so historical data stays traceable.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'fix', 'version' => null, 'title_en' => 'Items DB: de-duplicated entries'],
            [
                'audience' => 'web',
                'title_es' => 'Items DB: entradas duplicadas unificadas',
                'title_en' => 'Items DB: de-duplicated entries',
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
            ->where('title_en', 'Items DB: de-duplicated entries')
            ->delete();
    }
};
