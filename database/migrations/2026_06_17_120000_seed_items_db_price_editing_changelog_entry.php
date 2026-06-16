<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Items DB: edit prices on open'],
            [
                'audience' => 'web',
                'title_es' => 'Items DB: edita precios al abrir un item',
                'title_en' => 'Items DB: edit prices on open',
                'body_es' => <<<'MD'
Al abrir un item en **Items DB** ahora ves su **precio base (NPC)** y su **precio de mercado**. Si eres **admin, líder o contable**, puedes editar ambos ahí mismo, sin salir de la ficha. El resto los ve en modo lectura. El precio base no puede superar al de mercado (sigue siendo el suelo que el mercado no puede bajar). Cambiar un precio recalcula automáticamente los costes de crafteo que dependan de ese item.
MD,
                'body_en' => <<<'MD'
Opening an item in **Items DB** now shows its **base (NPC) price** and its **market price**. If you're an **admin, leader or accountant** you can edit both right there, without leaving the detail view. Everyone else sees them read-only. The base price can't exceed the market price (it's still the floor the market can't undercut). Changing a price automatically recomputes the craft costs that depend on that item.
MD,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')->where('title_en', 'Items DB: edit prices on open')->delete();
    }
};
