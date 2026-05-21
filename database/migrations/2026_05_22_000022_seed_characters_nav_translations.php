<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'nav.characters'              => ['es' => 'Personajes',         'en' => 'Characters'],
        'characters.page.title'       => ['es' => 'Personajes L2',      'en' => 'L2 characters'],
        'characters.page.subtitle'    => [
            'es' => 'Gestiona tu personaje principal y tus secundarios — separados de los datos de cuenta del perfil.',
            'en' => 'Manage your main character and secondaries — separate from the web-account fields in your profile.',
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
