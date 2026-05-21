<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'market_price.column_label'    => ['es' => 'Precio mercado',           'en' => 'Market price'],
        'market_price.value_column'    => ['es' => 'Valor',                    'en' => 'Value'],
        'market_price.warehouse_total' => ['es' => 'Valor estimado del stock', 'en' => 'Estimated stock value'],
        'market_price.warehouse_note'  => ['es' => 'sobre {priced} items con precio fijado', 'en' => 'over {priced} items priced'],
        'market_price.recipe_total'    => ['es' => 'Coste estimado',           'en' => 'Estimated cost'],
        'market_price.recipe_fee'      => ['es' => '(incluye fee de {fee})',   'en' => '(includes {fee} fee)'],
        'market_price.placeholder'     => ['es' => 'Sin precio',               'en' => 'No price'],
        'market_price.tooltip_updated' => ['es' => 'Actualizado por {user} hace {ago}', 'en' => 'Updated by {user} {ago} ago'],
        'market_price.edit_cta'        => ['es' => 'Click para editar',        'en' => 'Click to edit'],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                DB::table('translations')->updateOrInsert(
                    ['language' => $lang, 'key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('translations')
            ->whereIn('key', array_keys($this->translations))
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
