<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'loot.event_types.raid_boss' => [
            'es' => 'Raid Boss',
            'en' => 'Raid Boss',
        ],
        'loot.event_types.epic_boss' => [
            'es' => 'Epic Boss',
            'en' => 'Epic Boss',
        ],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                $exists = DB::table('translations')->where('key', $key)->where('language', $lang)->exists();
                if (!$exists) {
                    $rows[] = ['language' => $lang, 'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        if (!empty($rows)) {
            DB::table('translations')->insert($rows);
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
