<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'craft.will_be_auto_crafted'        => ['es' => 'Se auto-crafteará',                 'en' => 'Will auto-craft'],
        'craft.toast.auto_crafted'          => ['es' => 'Auto-crafteado: {items}',           'en' => 'Auto-crafted: {items}'],
        'craft.toast.produced'              => ['es' => 'Producido: {items}',                'en' => 'Produced: {items}'],
        'party.craft_bulk.left.pin_to_cp'   => ['es' => 'Fijar como prioridad en el CP',     'en' => 'Pin as priority in the CP'],
        'party.craft_bulk.left.pinned_ok'   => ['es' => 'Receta fijada en el CP',            'en' => 'Recipe pinned in the CP'],
        'party.craft_bulk.left.pinned_failed' => ['es' => 'No se pudo fijar la receta',      'en' => 'Could not pin the recipe'],
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
