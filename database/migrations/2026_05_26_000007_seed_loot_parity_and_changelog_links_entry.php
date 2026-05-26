<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
- **Paridad cards / lista en [/loot](/loot):** al expandir un report en modo lista ahora ves exactamente lo mismo que en modo card — image proof, items completos, asistentes, puntos, adena split y enlace al origen para reports derivados. Antes el modo lista mostraba solo la captura y un grid de items, perdiendo contexto.
- **Enlaces clicables en el changelog:** las menciones de rutas (como `/party`, `/loot` o `/itemsdb`) ahora son enlaces directos a la sección correspondiente. Se ha reescrito el historial existente para añadir los enlaces donde aplicaba.
MD;

        $bodyEn = <<<'MD'
- **Cards / list parity in [/loot](/loot):** expanding a row in list mode now shows exactly the same blocks as the card view — image proof, full items list, attendees, points, adena split and origin link for derived reports. List mode used to only show the screenshot and a small items grid, losing context.
- **Clickable links in the changelog:** route mentions (such as `/party`, `/loot` or `/itemsdb`) are now direct links to the corresponding section. The existing history was retroactively rewritten so older entries also have links where applicable.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Loot list/card parity + clickable changelog links'],
            [
                'audience' => 'web',
                'title_es' => 'Paridad lista/card en loot + enlaces clicables en el changelog',
                'title_en' => 'Loot list/card parity + clickable changelog links',
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
            ->where('title_en', 'Loot list/card parity + clickable changelog links')
            ->delete();
    }
};
