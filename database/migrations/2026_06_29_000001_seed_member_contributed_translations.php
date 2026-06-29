<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [];
        $translations = [
            'party.member.contributed_title' => [
                'es' => 'Lo aportado por {name}',
                'en' => '{name} Contributions',
                'it' => 'Contributi di {name}',
                'ru' => 'Вклад {name}',
            ],
            'party.member.contributed_empty' => [
                'es' => 'Sin ítems aportados en sesiones de farm.',
                'en' => 'No items contributed in farm sessions.',
                'it' => 'Nessun item contribuito nelle sessioni.',
                'ru' => 'Нет предметов в сессиях фарма.',
            ],
        ];

        $now = now();
        foreach ($translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                $rows[] = [
                    'language'   => $lang,
                    'key'        => $key,
                    'value'      => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($rows as $row) {
            DB::table('translations')->updateOrInsert(
                ['language' => $row['language'], 'key' => $row['key']],
                ['value' => $row['value'], 'updated_at' => $row['updated_at']]
            );
        }
    }

    public function down(): void
    {
        DB::table('translations')->whereIn('key', [
            'party.member.contributed_title',
            'party.member.contributed_empty',
        ])->delete();
    }
};
