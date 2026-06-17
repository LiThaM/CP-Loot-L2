<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill it/ru title+body for existing changelog entries from the
     * committed JSON (database/data/i18n/changelog.json). Matched by the
     * stable `title_en` (the natural key the seeds use), not auto-increment id.
     */
    public function up(): void
    {
        $path = base_path('database/data/i18n/changelog.json');
        if (! is_file($path)) {
            return;
        }
        $records = json_decode(file_get_contents($path), true);
        if (! is_array($records)) {
            return;
        }

        $now = now();
        foreach ($records as $r) {
            if (empty($r['title_en'])) {
                continue;
            }
            DB::table('changelog_entries')
                ->where('title_en', $r['title_en'])
                ->update([
                    'title_it' => $r['title_it'] ?? null,
                    'title_ru' => $r['title_ru'] ?? null,
                    'body_it' => $r['body_it'] ?? null,
                    'body_ru' => $r['body_ru'] ?? null,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('changelog_entries')->update([
            'title_it' => null,
            'title_ru' => null,
            'body_it' => null,
            'body_ru' => null,
        ]);
    }
};
