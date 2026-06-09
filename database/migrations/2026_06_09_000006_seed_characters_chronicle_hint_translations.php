<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'characters.chronicle_hint' => [
            'es' => 'Catálogo filtrado por la crónica {chronicle} de tu CP',
            'en' => 'Catalog filtered by your CP\'s {chronicle} chronicle',
        ],
        'characters.no_cp.title' => [
            'es' => 'Necesitas estar en una CP',
            'en' => 'You need to be in a CP',
        ],
        'characters.no_cp.hint' => [
            'es' => 'La gestión de personajes está ligada a la crónica de tu CP. Únete a una CP o crea la tuya desde el dashboard.',
            'en' => 'Character management is tied to your CP\'s chronicle. Join a CP or create your own from the dashboard.',
        ],
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
