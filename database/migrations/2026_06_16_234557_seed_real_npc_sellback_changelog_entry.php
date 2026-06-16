<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'improvement', 'version' => null, 'title_en' => 'Real NPC sell-back prices'],
            [
                'audience' => 'web',
                'title_es' => 'Precios base = venta real al NPC',
                'title_en' => 'Real NPC sell-back prices',
                'body_es' => <<<'MD'
El **precio base (NPC)** de los items ahora refleja lo que realmente te dan al venderlos en tienda, no el precio al que el NPC los vende. La fuente que usábamos listaba el precio de compra (≈ el doble), así que lo hemos ajustado a la mitad. Solo afecta a los items **sin precio de mercado** puesto (esos no cambian): su valor en el almacén y los puntos del tracker quedan más realistas.
MD,
                'body_en' => <<<'MD'
An item's **base (NPC) price** now reflects what you actually get when selling it at a store, not the price the NPC sells it for. Our source listed the buy price (≈ double), so we halved it. This only affects items **without a market price** set (those are unchanged): their warehouse value and tracker points are now more realistic.
MD,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')->where('title_en', 'Real NPC sell-back prices')->delete();
    }
};
