<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
- **Tutoriales:** hay un apartado nuevo en el menú de usuario, [/tutoriales](/tutoriales), donde se explica qué puede hacer cada rol. Cada sección lleva botones para lanzar mini-tours interactivos sobre la pantalla real — útil para descubrir cosas que igual no habías visto (los personajes secundarios del perfil, las normas del CP, el craft en bloque…).
- **Búsqueda de items más útil:** al buscar un item (al reportar loot, vender, añadir a wishlist…) salen primero los que se usan de verdad en la app, en vez de basura del catálogo. Adiós al "Load more" cinco veces.
MD;

        $bodyEn = <<<'MD'
- **Tutorials:** new section in the user menu, [/tutoriales](/tutoriales), explaining what each role can do. Every section has buttons that launch short interactive tours on the real screen — handy for surfacing features you might have missed (alt characters in your profile, CP rules, bulk crafting…).
- **Smarter item search:** when picking an item (loot report, sell modal, wishlist…) the ones actually used across the app surface first, instead of catalogue noise. Less clicking through "Load more".
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Tutorials section + smarter item search'],
            [
                'audience' => 'web',
                'title_es' => 'Sección de tutoriales + búsqueda de items más útil',
                'title_en' => 'Tutorials section + smarter item search',
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
            ->where('title_en', 'Tutorials section + smarter item search')
            ->delete();
    }
};
