<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Auction "Open" modal — wired to the warehouse picker
        'auction.open.item' => ['es' => 'Item del almacén CP', 'en' => 'Item from CP warehouse'],
        'auction.open.in_stock' => ['es' => 'En almacén', 'en' => 'In stock'],
        'auction.open.item_search' => ['es' => 'Buscar en el almacén…', 'en' => 'Search the warehouse…'],
        'auction.open.empty_warehouse' => ['es' => 'El almacén CP no tiene items.', 'en' => 'The CP warehouse is empty.'],
        'auction.open.no_match' => ['es' => 'Ningún item del almacén coincide.', 'en' => 'No warehouse item matches.'],
        'auction.open.max_amount' => ['es' => 'Máximo {n}', 'en' => 'Max {n}'],

        // Reused
        'common.change' => ['es' => 'Cambiar', 'en' => 'Change'],
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
