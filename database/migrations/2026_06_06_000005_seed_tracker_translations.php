<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Nav
        'nav.tracker' => ['es' => 'Tracker', 'en' => 'Tracker'],

        // CP settings (Settings tab)
        'cp.settings.tracker_enabled' => ['es' => 'DKP Value Tracker', 'en' => 'DKP Value Tracker'],
        'cp.settings.tracker_enabled_hint' => [
            'es' => 'Cuando está activo, cada item dropeado genera puntos = (precio de mercado / divisor) repartidos entre los attendees. Funciona en paralelo a los puntos por evento.',
            'en' => 'When on, each looted item awards points = (market price / divisor) split among attendees. Runs alongside the existing per-event points.',
        ],
        'cp.settings.tracker_divisor' => ['es' => 'Divisor', 'en' => 'Divisor'],
        'cp.settings.tracker_divisor_hint' => [
            'es' => 'Cuanto mayor el divisor, menos puntos por adena. 1000 es un valor estándar.',
            'en' => 'A higher divisor means fewer points per adena. 1000 is a sensible default.',
        ],

        // Tracker page
        'tracker.title' => ['es' => 'DKP Tracker', 'en' => 'DKP Tracker'],
        'tracker.subtitle' => [
            'es' => '{cp} · divisor {divisor}',
            'en' => '{cp} · divisor {divisor}',
        ],
        'tracker.leaderboard.title' => ['es' => 'Ranking', 'en' => 'Leaderboard'],
        'tracker.leaderboard.entries' => ['es' => 'entradas', 'en' => 'entries'],
        'tracker.leaderboard.empty' => [
            'es' => 'Aún no hay contribuciones. Activa la opción y confirma un reporte de loot para verlo.',
            'en' => 'No contributions yet. Enable the toggle and confirm a loot report to see them here.',
        ],
        'tracker.contributions.title' => ['es' => 'Contribuciones', 'en' => 'Contributions'],
        'tracker.contributions.empty' => ['es' => 'No hay contribuciones con esos filtros.', 'en' => 'No contributions match those filters.'],
        'tracker.filter.all_members' => ['es' => 'Todos los miembros', 'en' => 'All members'],
        'tracker.filter.all_badges' => ['es' => 'Todos los badges', 'en' => 'All badges'],
        'tracker.col.date' => ['es' => 'Fecha', 'en' => 'Date'],
        'tracker.col.member' => ['es' => 'Miembro', 'en' => 'Member'],
        'tracker.col.badge' => ['es' => 'Badge', 'en' => 'Badge'],
        'tracker.col.description' => ['es' => 'Descripción', 'en' => 'Description'],
        'tracker.col.points' => ['es' => 'Puntos', 'en' => 'Points'],

        // Add modal
        'tracker.add.cta' => ['es' => 'Añadir contribución', 'en' => 'Add contribution'],
        'tracker.add.title' => ['es' => 'Nueva contribución', 'en' => 'New contribution'],
        'tracker.add.members' => ['es' => 'Miembros', 'en' => 'Members'],
        'tracker.add.members_hint' => [
            'es' => 'Mantén Ctrl/⌘ para seleccionar varios.',
            'en' => 'Hold Ctrl/⌘ to select multiple.',
        ],
        'tracker.add.description' => ['es' => 'Descripción', 'en' => 'Description'],
        'tracker.add.points' => ['es' => 'Puntos', 'en' => 'Points'],
        'tracker.add.points_hint_split' => [
            'es' => 'Se reparte entre los seleccionados (puntos / N).',
            'en' => 'Split among selected members (points / N).',
        ],
        'tracker.add.points_hint_event' => [
            'es' => 'EVENT: cada miembro seleccionado recibe estos puntos completos.',
            'en' => 'EVENT: each selected member receives this full amount.',
        ],
        'tracker.add.is_event' => ['es' => 'Bonus EVENT (flat por miembro)', 'en' => 'EVENT bonus (flat per member)'],
        'tracker.add.is_event_hint' => [
            'es' => 'Útil para premios de asistencia o pots semanales: cada miembro recibe los puntos completos.',
            'en' => 'Useful for attendance prizes or weekly pots: each member receives the full amount.',
        ],
        'tracker.add.success' => ['es' => 'Contribución registrada', 'en' => 'Contribution recorded'],
        'tracker.add.error' => ['es' => 'No se pudo registrar', 'en' => 'Could not record'],

        // Delete confirm
        'tracker.delete.confirm_title' => ['es' => '¿Borrar contribución?', 'en' => 'Delete contribution?'],
        'tracker.delete.confirm_text' => [
            'es' => 'Se eliminará la entrada del ledger. No se puede deshacer.',
            'en' => 'The ledger entry will be removed. Cannot be undone.',
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
