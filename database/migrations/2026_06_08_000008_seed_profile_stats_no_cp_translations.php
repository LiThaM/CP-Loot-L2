<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Friendly empty state for users (admins / orphans) without a CP.
        'profile.stats.no_cp.title' => [
            'es' => 'Las estadísticas personales necesitan una CP',
            'en' => 'Personal stats require a CP',
        ],
        'profile.stats.no_cp.hint' => [
            'es' => 'Únete a una CP existente o crea la tuya para ver aquí tus reports, ranking, puntos e histórico de adena.',
            'en' => 'Join an existing CP or create your own to see your reports, rank, points and adena history here.',
        ],
        'profile.stats.no_cp.dashboard_cta' => [
            'es' => 'Volver al dashboard',
            'en' => 'Back to dashboard',
        ],
        'profile.stats.no_cp.characters_cta' => [
            'es' => 'Gestionar personajes',
            'en' => 'Manage characters',
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
