<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Nav dropdown labels
        'nav.cp' => ['es' => 'CP', 'en' => 'CP'],
        'nav.more' => ['es' => 'Más', 'en' => 'More'],
        'nav.manage' => ['es' => 'Administrar', 'en' => 'Manage'],
        'nav.system' => ['es' => 'Sistema', 'en' => 'System'],
        'nav.tutorials' => ['es' => 'Tutoriales', 'en' => 'Tutorials'],
        'nav.loot.pending_history' => ['es' => 'Pendientes & historial', 'en' => 'Pending & history'],
        'nav.cp.stats' => ['es' => 'Estadísticas avanzadas', 'en' => 'Stats deep-dive'],

        // CP Stats page
        'cp.stats.title' => ['es' => 'Estadísticas del CP', 'en' => 'CP Stats'],
        'cp.stats.kicker' => ['es' => 'Análisis avanzado', 'en' => 'CP deep-dive'],
        'cp.stats.days_suffix' => ['es' => 'd', 'en' => 'd'],

        // KPIs
        'cp.stats.kpi.reports' => ['es' => 'Reports', 'en' => 'Reports'],
        'cp.stats.kpi.adena_in' => ['es' => 'Adena entrante', 'en' => 'Adena in'],
        'cp.stats.kpi.adena_out' => ['es' => 'Adena pagada', 'en' => 'Adena out'],
        'cp.stats.kpi.vault_value' => ['es' => 'Valor vault', 'en' => 'Vault value'],
        'cp.stats.kpi.active_members' => ['es' => 'Miembros activos', 'en' => 'Active members'],

        // Charts
        'cp.stats.report_trend' => ['es' => 'Tendencia de reports (por tipo)', 'en' => 'Report trend (by event type)'],
        'cp.stats.adena_flow' => ['es' => 'Flujo de adena (entrada vs salida)', 'en' => 'Adena flow (in vs out)'],
        'cp.stats.adena_in' => ['es' => 'Entrada', 'en' => 'In'],
        'cp.stats.adena_out' => ['es' => 'Salida', 'en' => 'Out'],
        'cp.stats.grade_distribution' => ['es' => 'Vault por grade', 'en' => 'Vault by grade'],

        // Top items table
        'cp.stats.top_items' => ['es' => 'Items más dropeados', 'en' => 'Top items dropped'],
        'cp.stats.col.item' => ['es' => 'Item', 'en' => 'Item'],
        'cp.stats.col.grade' => ['es' => 'Grade', 'en' => 'Grade'],
        'cp.stats.col.drops' => ['es' => 'Drops', 'en' => 'Drops'],
        'cp.stats.col.qty' => ['es' => 'Cant.', 'en' => 'Qty'],
        'cp.stats.col.value' => ['es' => 'Valor est.', 'en' => 'Est. value'],
        'cp.stats.no_items' => ['es' => 'Sin drops en este periodo.', 'en' => 'No drops in this period.'],
        'cp.stats.no_vault' => ['es' => 'El vault está vacío.', 'en' => 'Vault is empty.'],

        // Heatmap
        'cp.stats.heatmap' => ['es' => 'Mapa de actividad por miembro', 'en' => 'Member activity heatmap'],
        'cp.stats.col.member' => ['es' => 'Miembro', 'en' => 'Member'],
        'cp.stats.col.total' => ['es' => 'Total', 'en' => 'Total'],
        'cp.stats.no_activity' => ['es' => 'Sin actividad de miembros en este periodo.', 'en' => 'No member activity in this period.'],

        // Financial scoreboard
        'cp.stats.financial' => ['es' => 'Resumen financiero del CP', 'en' => 'CP financial scoreboard'],
        'cp.stats.paid_ratio' => ['es' => 'Pagado {ratio}%', 'en' => 'Paid {ratio}%'],
        'cp.stats.total_gained' => ['es' => 'Ganado', 'en' => 'Gained'],
        'cp.stats.total_paid' => ['es' => 'Pagado', 'en' => 'Paid'],
        'cp.stats.total_owed' => ['es' => 'Pendiente', 'en' => 'Owed'],
        'cp.stats.top_owed' => ['es' => 'Mayores pendientes', 'en' => 'Top owed'],

        // Tracker top
        'cp.stats.tracker_top' => ['es' => 'DKP Tracker — top 5', 'en' => 'DKP Tracker — top 5'],
        'cp.stats.see_all' => ['es' => 'Ver todo', 'en' => 'See all'],
        'cp.stats.no_tracker' => ['es' => 'Sin contribuciones del tracker en este periodo.', 'en' => 'No tracker contributions in this period.'],
        'cp.stats.entries' => ['es' => 'entradas', 'en' => 'entries'],

        // Empty state
        'cp.stats.empty.title' => ['es' => 'Aún no hay actividad en este periodo', 'en' => 'No activity yet in this period'],
        'cp.stats.empty.hint' => ['es' => 'En cuanto los miembros confirmen reports de loot, los gráficos se irán llenando aquí.', 'en' => 'Once members confirm loot reports the charts will fill up here.'],
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
