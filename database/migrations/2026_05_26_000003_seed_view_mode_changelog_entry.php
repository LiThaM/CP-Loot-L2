<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Vista cards / lista global.** Cada listado grande (CP Vault items, mi warehouse personal, wishlist) tiene ahora un botón con dos iconos al lado del buscador: grid y lista. Pulsa el que quieras y se aplica a **todas** las secciones del app a la vez. La preferencia se guarda en tu navegador y se sincroniza entre pestañas. El modo lista compacta cada item en una fila tipo tabla — más items visibles sin scroll, ideal para CPs con muchos items.

Primera iteración cubre: CP Vault warehouse, mi warehouse personal y wishlist. Otras secciones (loot history, pinned recipes, CP requests) llegan en una próxima ronda.
MD;

        $bodyEn = <<<'MD'
**Global cards / list view.** Every large listing (CP Vault items, my personal warehouse, wishlist) now has a small grid/list toggle next to the search box. Pick one and it applies to **every** section at once. The choice lives in your browser and syncs across tabs. List mode collapses each item into a compact table row — more items visible without scrolling, ideal for CPs with lots of stock.

First iteration covers: CP Vault warehouse, personal warehouse, wishlist. Other sections (loot history, pinned recipes, CP requests) follow in a next pass.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Global cards / list view toggle'],
            [
                'audience' => 'web',
                'title_es' => 'Toggle global vista cards / lista',
                'title_en' => 'Global cards / list view toggle',
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
            ->where('title_en', 'Global cards / list view toggle')
            ->delete();
    }
};
