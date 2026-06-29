<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $entries = [
            ['language' => 'es', 'key' => 'loot.filter_member_placeholder', 'value' => 'Filtrar miembro...'],
            ['language' => 'en', 'key' => 'loot.filter_member_placeholder', 'value' => 'Filter member...'],
            ['language' => 'it', 'key' => 'loot.filter_member_placeholder', 'value' => 'Filtra membro...'],
            ['language' => 'ru', 'key' => 'loot.filter_member_placeholder', 'value' => 'Фильтр участника...'],
        ];

        foreach ($entries as $row) {
            DB::table('translations')->updateOrInsert(
                ['language' => $row['language'], 'key' => $row['key']],
                ['value' => $row['value'], 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('translations')->where('key', 'loot.filter_member_placeholder')->delete();
    }
};
