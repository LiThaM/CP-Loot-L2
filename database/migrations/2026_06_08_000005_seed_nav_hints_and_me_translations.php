<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // === Me dropdown labels ===
        'nav.me' => ['es' => 'Yo', 'en' => 'Me'],
        'nav.me.profile' => ['es' => 'Mi perfil', 'en' => 'My profile'],
        'nav.me.characters' => ['es' => 'Mis personajes', 'en' => 'My characters'],
        'nav.me.stats' => ['es' => 'Mis estadísticas', 'en' => 'My stats'],
        'nav.me.tickets' => ['es' => 'Mis tickets', 'en' => 'My tickets'],

        // === Hints — CP dropdown ===
        'nav.cp.members_hint' => ['es' => 'Roster, puntos y balances de adena', 'en' => 'Roster, points and adena balances'],
        'nav.cp.warehouse_hint' => ['es' => 'Almacén compartido del CP', 'en' => 'Shared CP warehouse'],
        'nav.cp.stats_hint' => ['es' => 'Gráficos, top items, heatmap', 'en' => 'Charts, top items, heatmap'],
        'nav.cp.tracker_hint' => ['es' => 'DKP value-based — ledger del CP', 'en' => 'Value-based DKP ledger'],
        'nav.cp.external_payouts_hint' => ['es' => 'Adena pendiente a externos', 'en' => 'Adena owed to non-members'],

        // === Hints — Loot dropdown ===
        'nav.loot.pending_history_hint' => ['es' => 'Aprueba drops y revisa el histórico', 'en' => 'Approve drops, review history'],

        // === Hints — Me dropdown ===
        'nav.me.profile_hint' => ['es' => 'Cuenta y preferencias', 'en' => 'Account and preferences'],
        'nav.me.characters_hint' => ['es' => 'Alta y edición de personajes', 'en' => 'Register and edit characters'],
        'nav.me.warehouse_hint' => ['es' => 'Tu adena e items personales', 'en' => 'Your personal adena and items'],
        'nav.me.stats_hint' => ['es' => 'Tu ranking, charts y actividad', 'en' => 'Your rank, charts and activity'],
        'nav.me.tickets_hint' => ['es' => 'Tus solicitudes de soporte', 'en' => 'Your support requests'],

        // === Top-level Items DB hint ===
        'nav.items_db_hint' => ['es' => 'Catálogo global de items', 'en' => 'Global item catalogue'],

        // === Hints — Admin Manage dropdown ===
        'nav.manage.cps_hint' => ['es' => 'Todas las guilds + onboarding', 'en' => 'All guilds and onboarding'],
        'nav.manage.users_hint' => ['es' => 'Búsqueda, ban, auditoría, impersonate', 'en' => 'Search, ban, audit, impersonate'],
        'nav.manage.items_hint' => ['es' => 'CRUD del catálogo + precio de mercado', 'en' => 'Catalogue CRUD + market price'],
        'nav.manage.translations_hint' => ['es' => 'Keys i18n (ES/EN)', 'en' => 'i18n keys (ES/EN)'],

        // === Hints — Admin System dropdown ===
        'nav.system.releases_hint' => ['es' => 'Versiones del cliente desktop', 'en' => 'Desktop client versions'],
        'nav.system.crashes_hint' => ['es' => 'Error tracking + fingerprints', 'en' => 'Error tracking and fingerprints'],
        'nav.system.tickets_hint' => ['es' => 'Bandeja de soporte', 'en' => 'Support inbox'],

        // === Profile stats page ===
        'profile.stats.title' => ['es' => 'Mis estadísticas', 'en' => 'My stats'],
        'profile.stats.kicker' => ['es' => 'Estadísticas personales', 'en' => 'My personal stats'],
        'profile.stats.kpi.total_points' => ['es' => 'Puntos totales', 'en' => 'Total points'],
        'profile.stats.kpi.adena_gained' => ['es' => 'Adena ganada', 'en' => 'Adena gained'],
        'profile.stats.kpi.adena_owed' => ['es' => 'Adena pendiente', 'en' => 'Adena owed'],
        'profile.stats.kpi.reports' => ['es' => 'Reports enviados', 'en' => 'Reports submitted'],
        'profile.stats.kpi.characters' => ['es' => 'Personajes', 'en' => 'Characters'],

        'profile.stats.rank_kicker' => ['es' => 'Tu posición en el CP', 'en' => 'Your CP rank'],
        'profile.stats.rank_value' => ['es' => '#{position} de {total}', 'en' => '#{position} of {total}'],
        'profile.stats.rank_points' => ['es' => '{points} puntos en total', 'en' => '{points} points total'],

        'profile.stats.points_timeline' => ['es' => 'Puntos ganados por día', 'en' => 'Points earned per day'],
        'profile.stats.points_daily' => ['es' => 'Puntos/día', 'en' => 'Points/day'],
        'profile.stats.adena_flow' => ['es' => 'Flujo de adena (entrada vs salida)', 'en' => 'Adena flow (in vs out)'],

        'profile.stats.top_items' => ['es' => 'Items que más he recibido', 'en' => 'Top items I received'],
        'profile.stats.col.awards' => ['es' => 'Asignados', 'en' => 'Awards'],
        'profile.stats.no_items' => ['es' => 'No te han asignado items en este periodo.', 'en' => 'No items assigned to you in this period.'],

        'profile.stats.my_tracker' => ['es' => 'Mi DKP tracker', 'en' => 'My DKP tracker'],
        'profile.stats.no_tracker' => ['es' => 'Aún sin contribuciones del tracker.', 'en' => 'No tracker contributions yet.'],
        'profile.stats.tracker_of' => ['es' => 'de {total} contribuyentes', 'en' => 'of {total} contributors'],

        'profile.stats.activity_calendar' => ['es' => 'Mi calendario de actividad', 'en' => 'My activity calendar'],
        'profile.stats.less' => ['es' => 'menos', 'en' => 'less'],
        'profile.stats.more' => ['es' => 'más', 'en' => 'more'],

        'profile.stats.my_characters' => ['es' => 'Mis personajes', 'en' => 'My characters'],
        'profile.stats.manage' => ['es' => 'Gestionar', 'en' => 'Manage'],
        'profile.stats.no_characters' => ['es' => 'Aún no tienes personajes registrados.', 'en' => 'You have no characters registered yet.'],
        'profile.stats.add_one' => ['es' => 'Añadir tu primer personaje', 'en' => 'Add your first character'],
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
