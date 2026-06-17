<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'loot.donated_by' => [
            'es' => 'Donado por',
            'en' => 'Donated by',
            'it' => 'Donato da',
            'ru' => 'Пожертвовал',
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
        DB::table('translations')->where('key', 'loot.donated_by')->whereIn('language', ['es', 'en', 'it', 'ru'])->delete();
    }
};
