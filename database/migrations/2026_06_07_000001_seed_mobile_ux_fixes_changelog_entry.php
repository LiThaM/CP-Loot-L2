<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Varios usuarios nos comentaron que **en móvil algunos apartados quedaban tapados** por la nav inferior o que **los modals se cortaban lateralmente**. Pasada doble de fix:

- La barra inferior fija ya no tapa la última fila de las tablas (loot, /party, /tracker) y respeta la safe-area de iOS (notch + indicador de inicio).
- Modals de toda la app (registrar loot, vender, asignar, normas del CP, changelog, soporte, etc.) usan ahora ancho mobile-safe: en móvil ocupan casi toda la pantalla con un margen mínimo, en sm+ vuelven a su tamaño habitual.
- Tablas anchas de Loot y CP Vault: ahora deslizan horizontalmente en lugar de cortarse.
- Lista de miembros del CP: en móvil pasa a una columna y el bloque de adena/acciones se acomoda sin pisarse.
- Calculadora de recetas: en móvil deja de comprimirse — vuelves a ver toda la lista de materiales.
- Dropdown de alertas: ya no se sale por la derecha en pantallas estrechas y scrollea si tienes muchas.

Si seguís viendo algo que se solapa o no se ve, decidnos por soporte con el modelo de teléfono.
MD;

        $bodyEn = <<<'MD'
Several users mentioned that **on mobile some sections were hidden** behind the bottom nav and that **modals were getting cut off sideways**. Two-pass fix:

- The fixed bottom nav no longer hides the last row of tables (loot, /party, /tracker) and respects the iOS safe-area (notch + home indicator).
- Modals across the app (loot session, sell, assign, CP rules, changelog, support, etc.) now use a mobile-safe width: on phones they take almost the full screen with a small margin, on sm+ they go back to their usual size.
- Wide tables in Loot and CP Vault now scroll horizontally instead of clipping.
- Member list in CP: on mobile it collapses to a single column and the adena/actions block wraps instead of overlapping.
- Recipe calculator: stops squashing on mobile — you can see the full materials list again.
- Alerts dropdown: no longer overflows the right edge on narrow screens and scrolls when you have many.

If you still see anything overlapping or hidden, ping support with your phone model.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'fix', 'version' => null, 'title_en' => 'Mobile layout fixes (overlapping sections + clipped modals)'],
            [
                'audience' => 'web',
                'title_es' => 'Fixes de layout en móvil (apartados que se solapaban y modals cortados)',
                'title_en' => 'Mobile layout fixes (overlapping sections + clipped modals)',
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
            ->where('title_en', 'Mobile layout fixes (overlapping sections + clipped modals)')
            ->delete();
    }
};
