<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Precios de mercado por item.** Ahora puedes fijar un precio en adena para cada item, **por crónica**. El precio se comparte entre todas las CPs de la misma crónica (LU4 con LU4, C5 con C5; no se cruzan).

- **`/recipes`**: cada material muestra una celda editable de "Precio mercado". Click → escribe el valor → Enter. El total estimado del craft (`coste de materiales + fee`) se recalcula.
- **`/party` warehouse**: nueva columna "Precio" (editable) y "Valor" (qty × precio) por fila, más un banner con el valor estimado total del stock del CP.
- **`/itemsdb`**: tabla con la nueva columna, ordenable y editable inline.

Cualquier usuario autenticado puede actualizar precios (estilo wiki). Pasa el cursor sobre la celda para ver quién lo cambió y hace cuánto.
MD;

        $bodyEn = <<<'MD'
**Per-item market prices.** You can now set an adena price for each item, **per chronicle**. Prices are shared across all CPs in the same chronicle (LU4 with LU4, C5 with C5; never cross).

- **`/recipes`**: each material shows an editable "Market price" cell. Click → type → Enter. The estimated craft cost (`materials + fee`) recalculates.
- **`/party` warehouse**: new "Price" column (editable) and "Value" column (qty × price) per row, plus a banner with the CP stock's total estimated value.
- **`/itemsdb`**: new column on the table, editable inline.

Any authenticated user can update prices (wiki-style). Hover the cell to see who last changed it and when.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Market prices per item (chronicle-scoped)'],
            [
                'audience' => 'web',
                'title_es' => 'Precios de mercado por item (por crónica)',
                'title_en' => 'Market prices per item (chronicle-scoped)',
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
            ->where('title_en', 'Market prices per item (chronicle-scoped)')
            ->delete();
    }
};
