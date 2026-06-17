<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Italian & Russian now available'],
            [
                'audience' => 'web',
                'title_es' => 'Italiano y ruso disponibles',
                'title_en' => 'Italian & Russian now available',
                'body_es' => 'La web ya está disponible en **italiano** y **ruso**, además de español e inglés. Cámbialo en Perfil → Preferencias → Idioma. Los nombres de los items se mantienen en inglés (estándar del juego).',
                'body_en' => 'The site is now available in **Italian** and **Russian**, besides Spanish and English. Switch it in Profile → Preferences → Language. Item names stay in English (the game standard).',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')->where('title_en', 'Italian & Russian now available')->delete();
    }
};
