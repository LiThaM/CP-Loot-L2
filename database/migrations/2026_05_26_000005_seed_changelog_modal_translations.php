<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'changelog.modal.kicker'      => ['es' => 'Novedades',                            'en' => "What's new"],
        'changelog.modal.title'       => ['es' => 'Cambios desde tu última visita',       'en' => 'Changes since your last visit'],
        'changelog.modal.see_all'     => ['es' => 'Ver historial completo',               'en' => 'See full changelog'],
        'changelog.modal.acknowledge' => ['es' => 'Visto, no me lo muestres más',         'en' => "Got it, don't show again"],
        'common.date'                 => ['es' => 'Fecha',                                'en' => 'Date'],
        'common.status'               => ['es' => 'Estado',                               'en' => 'Status'],
        'loot.event_type'             => ['es' => 'Tipo',                                 'en' => 'Type'],
        'loot.items'                  => ['es' => 'Items',                                'en' => 'Items'],
        'loot.attendees'              => ['es' => 'Attendees',                            'en' => 'Attendees'],
        'loot.reported_by_label'      => ['es' => 'Reportado por',                        'en' => 'Reported by'],
        'loot.no_pending_loot'        => ['es' => 'Sin reports pendientes.',              'en' => 'No pending reports.'],
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
