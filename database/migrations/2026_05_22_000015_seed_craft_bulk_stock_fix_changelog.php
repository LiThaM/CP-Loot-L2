<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Mejoras en `/party/craft-bulk`:**

- **Fix:** el planner ahora descuenta el stock del almacén CP de **cualquier** material intermedio craftable, no sólo de los que aparecen como material directo del recipe ordenado. Antes, si tenías p. ej. 500 Cord en el almacén pero el Cord sólo aparecía como sub-craft (depth 2+), el stock se ignoraba y el Thread necesario quedaba inflado.
- **Nuevo:** cada sub-craft de la lista inferior es desplegable. Al pulsarlo se muestra su receta directa con la cantidad por craft y el total a producir (ej. `× 25 Thread por craft → 37 200 totales`).
MD;

        $bodyEn = <<<'MD'
**Improvements to `/party/craft-bulk`:**

- **Fix:** the planner now subtracts warehouse stock for **any** intermediate craftable material, not only those listed as direct materials of the ordered recipe. Before, e.g. having 500 Cord in the warehouse was ignored when Cord only appeared as a sub-craft (depth 2+), inflating the Thread total.
- **New:** each sub-craft in the bottom list is expandable. Click it to see its direct recipe with per-craft quantity and total (e.g. `× 25 Thread per craft → 37,200 total`).
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Bulk craft: deep stock fix + expandable sub-crafts'],
            [
                'audience' => 'web',
                'title_es' => 'Craft masivo: fix de stock profundo + sub-crafts desplegables',
                'title_en' => 'Bulk craft: deep stock fix + expandable sub-crafts',
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
            ->where('title_en', 'Bulk craft: deep stock fix + expandable sub-crafts')
            ->delete();
    }
};
