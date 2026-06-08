<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'tutorials.topic.me_section.title' => [
            'es' => 'Tu sección "Yo"',
            'en' => 'Your "Me" section',
        ],
        'tutorials.topic.me_section.intro' => [
            'es' => 'Todo lo que es tuyo está agrupado bajo **Yo ▼** en el menú principal. Perfil, personajes, almacén personal, estadísticas y tickets en un solo lugar.',
            'en' => 'Everything that belongs to you sits under **Me ▼** in the main menu. Profile, characters, personal warehouse, stats and tickets — all in one place.',
        ],
        'tutorials.topic.me_section.bullet.0' => [
            'es' => '**Mi perfil**: ajustes de cuenta (email, contraseña), preferencias (idioma, tema), avisos por mail.',
            'en' => '**My profile**: account settings (email, password), preferences (language, theme), email alerts.',
        ],
        'tutorials.topic.me_section.bullet.1' => [
            'es' => '**Mis personajes**: dar de alta cada uno de tus chars (nombre, clase L2, level). El loot modal usa estos datos para destacar items de tu raza.',
            'en' => '**My characters**: register each of your chars (name, L2 class, level). The loot modal uses these to highlight items of your race.',
        ],
        'tutorials.topic.me_section.bullet.2' => [
            'es' => '**Mi almacén**: tu adena pendiente y los items que te han asignado. Antes estaba bajo Loot, ahora vive aquí como "lo mío".',
            'en' => '**My warehouse**: your pending adena and items assigned to you. Used to live under Loot, now sits here as "my stuff".',
        ],
        'tutorials.topic.me_section.bullet.3' => [
            'es' => '**Mis estadísticas** ([/profile/stats](/profile/stats)): KPIs personales, gráficos de puntos y adena por día, top items que recibes, tu posición en el ranking del CP y en el DKP tracker, calendario de actividad.',
            'en' => '**My stats** ([/profile/stats](/profile/stats)): personal KPIs, daily points and adena charts, top items you receive, your rank in the CP and DKP tracker, activity calendar.',
        ],
        'tutorials.topic.me_section.bullet.4' => [
            'es' => '**Mis tickets**: solicitudes de soporte abiertas y resueltas. Se movieron desde el menú "More" para que tu apartado quede completo.',
            'en' => '**My tickets**: open and resolved support requests. Moved here from the "More" menu so your section is complete.',
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
