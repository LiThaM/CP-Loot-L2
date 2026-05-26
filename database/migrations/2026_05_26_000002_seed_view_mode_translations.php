<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'view_mode.cards'  => ['es' => 'Cards',              'en' => 'Cards'],
        'view_mode.list'   => ['es' => 'Lista',              'en' => 'List'],
        'view_mode.toggle' => ['es' => 'Cambiar vista',      'en' => 'Toggle view'],
        'common.grade'     => ['es' => 'Grade',              'en' => 'Grade'],
        'loot.priority'    => ['es' => 'Prioridad',          'en' => 'Priority'],
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
