<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Loot modal — external attendees
        'loot.attendees.title'              => ['es' => 'Asistentes',                                 'en' => 'Attendees'],
        'loot.attendees.cp_members_label'   => ['es' => 'Miembros de la CP',                          'en' => 'CP members'],
        'loot.attendees.add_external'       => ['es' => '+ Añadir externo',                           'en' => '+ Add external'],
        'loot.attendees.external_badge'     => ['es' => 'externo',                                    'en' => 'external'],
        'loot.attendees.external_name_ph'   => ['es' => 'Nick del jugador externo',                   'en' => 'External player nick'],
        'loot.attendees.remove'             => ['es' => 'Quitar',                                     'en' => 'Remove'],

        // Loot modal — split
        'loot.split.cp_share'               => ['es' => 'Porcentaje al fondo de la CP',               'en' => 'CP fund percentage'],
        'loot.split.preview.cp_fund'        => ['es' => 'Fondo de la CP',                             'en' => 'CP fund'],
        'loot.split.preview.per_member'     => ['es' => 'Cada asistente',                             'en' => 'Each attendee'],
        'loot.split.preview.external_owed'  => ['es' => 'A pagar a externos',                         'en' => 'Owed to externals'],
        'loot.split.preview.no_attendees'   => ['es' => 'Sin asistentes — todo va al fondo de la CP', 'en' => 'No attendees — everything goes to the CP fund'],

        // Sell modal — source session picker
        'sell.source_session.label'         => ['es' => 'Sesión de farm origen',                      'en' => 'Source farm session'],
        'sell.source_session.placeholder'   => ['es' => 'Elige la sesión que farmeó este ítem',       'en' => 'Pick the session that farmed this item'],
        'sell.source_session.empty'         => ['es' => 'No hay sesiones con stock pendiente para este ítem.', 'en' => 'No sessions with pending stock for this item.'],
        'sell.source_session.pending'       => ['es' => '{n} pendientes',                              'en' => '{n} pending'],

        // Sell modal — split summary
        'sell.split.cp_share'               => ['es' => 'Fondo de la CP (%)',                         'en' => 'CP fund (%)'],
        'sell.split.summary.cp'             => ['es' => 'Al fondo de la CP',                          'en' => 'To the CP fund'],
        'sell.split.summary.each'           => ['es' => 'A cada asistente',                           'en' => 'To each attendee'],
        'sell.split.summary.externals'      => ['es' => 'Por pagar a externos',                       'en' => 'Owed to externals'],

        // System / External payouts page
        'system.external_payouts.title'             => ['es' => 'Pagos a externos',                  'en' => 'External payouts'],
        'system.external_payouts.subtitle'          => ['es' => 'Adena que debes pagar fuera del sistema a jugadores no-CP que han farmeado contigo', 'en' => 'Adena you owe outside the system to non-CP players who farmed with you'],
        'system.external_payouts.filter.pending'    => ['es' => 'Pendientes',                         'en' => 'Pending'],
        'system.external_payouts.filter.paid'       => ['es' => 'Pagados',                            'en' => 'Paid'],
        'system.external_payouts.filter.all'        => ['es' => 'Todos',                              'en' => 'All'],
        'system.external_payouts.col.name'          => ['es' => 'Externo',                            'en' => 'External'],
        'system.external_payouts.col.amount'        => ['es' => 'Adena a pagar',                      'en' => 'Adena owed'],
        'system.external_payouts.col.sell_report'   => ['es' => 'Venta',                              'en' => 'Sale'],
        'system.external_payouts.col.paid_at'       => ['es' => 'Pagado en',                          'en' => 'Paid at'],
        'system.external_payouts.action.mark_paid'  => ['es' => 'Marcar pagado',                      'en' => 'Mark paid'],
        'system.external_payouts.confirm.title'     => ['es' => '¿Marcar como pagado?',               'en' => 'Mark as paid?'],
        'system.external_payouts.confirm.text'      => ['es' => 'Se registrará que ya pagaste a {name} fuera del sistema.', 'en' => 'This will record that you paid {name} outside the system.'],
        'system.external_payouts.empty.pending'     => ['es' => 'No tienes pagos pendientes a externos.', 'en' => 'No pending external payouts.'],
        'system.external_payouts.empty.paid'        => ['es' => 'Aún no has marcado ningún pago como liquidado.', 'en' => 'No payouts marked as paid yet.'],
        'system.external_payouts.nav'               => ['es' => 'Pagos externos',                     'en' => 'External payouts'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                $exists = DB::table('translations')
                    ->where('key', $key)
                    ->where('language', $lang)
                    ->exists();

                if (! $exists) {
                    $rows[] = [
                        'language'   => $lang,
                        'key'        => $key,
                        'value'      => $value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (! empty($rows)) {
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
