<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'nav.craft'                  => ['es' => 'Craft',                              'en' => 'Craft'],
        'nav.craft.individual'       => ['es' => 'Craft Individual',                   'en' => 'Individual craft'],
        'nav.craft.individual_hint'  => ['es' => 'Recetas fijadas del CP',             'en' => 'CP pinned recipes'],
        'nav.craft.bulk'             => ['es' => 'Craft Masivo',                       'en' => 'Bulk craft'],
        'nav.craft.bulk_hint'        => ['es' => 'Planner con múltiples recetas',      'en' => 'Multi-recipe planner'],
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
