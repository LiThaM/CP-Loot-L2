<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Cada ítem tiene ahora un **precio base** scrapeado del juego (lo que paga el NPC) además del **precio de mercado** que pones tú. En el [CP Vault](/party) y en el [explorador de recetas](/recipes) verás el precio base en gris cursiva cuando nadie ha fijado precio de mercado — así sabes el suelo mínimo sin tener que mirarlo a mano. Al fijar tu propio precio, el servidor rechaza cualquier valor por debajo del base (no tiene sentido vender por menos de lo que paga el NPC).
MD;

        $bodyEn = <<<'MD'
Every item now has a **base price** scraped from the game (what the NPC pays) in addition to the **market price** you set. In the [CP Vault](/party) and the [recipe explorer](/recipes) you'll see the base price in italic grey when nobody has set a market price yet — so you know the floor without looking it up. When you set your own price, the server rejects anything below the base (no point selling for less than the NPC pays).
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Item base price (NPC) as fallback'],
            [
                'audience' => 'web',
                'title_es' => 'Precio base del ítem (NPC) como respaldo',
                'title_en' => 'Item base price (NPC) as fallback',
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
            ->where('title_en', 'Item base price (NPC) as fallback')
            ->delete();
    }
};
