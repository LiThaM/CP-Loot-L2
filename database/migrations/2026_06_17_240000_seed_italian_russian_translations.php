<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the full Italian (it) and Russian (ru) UI translation sets from
     * the committed JSON files (database/data/i18n/{it,ru}.json), produced by
     * translating the 1571 en/es keys. Item names are NOT translated (game
     * standard is English) — those live in the items table, not here.
     */
    public function up(): void
    {
        $now = now();
        foreach (['it', 'ru'] as $lang) {
            $path = base_path("database/data/i18n/{$lang}.json");
            if (! is_file($path)) {
                continue;
            }
            $map = json_decode(file_get_contents($path), true);
            if (! is_array($map)) {
                continue;
            }

            // Idempotent: replace any existing rows for this language.
            DB::table('translations')->where('language', $lang)->delete();

            $rows = [];
            foreach ($map as $key => $value) {
                $rows[] = [
                    'language' => $lang,
                    'key' => (string) $key,
                    'value' => (string) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('translations')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        DB::table('translations')->whereIn('language', ['it', 'ru'])->delete();
    }
};
