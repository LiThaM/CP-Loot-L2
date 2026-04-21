<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Page title & header
        'tickets.title'                     => ['es' => 'Tickets',                                     'en' => 'Tickets'],
        'tickets.subtitle.admin'            => ['es' => 'Panel de administración',                     'en' => 'Administration panel'],
        'tickets.subtitle.leader'           => ['es' => 'Tickets asignados y creados',                 'en' => 'Assigned and created tickets'],
        'tickets.subtitle.member'           => ['es' => 'Mis tickets',                                 'en' => 'My tickets'],

        // Buttons
        'tickets.btn.new'                   => ['es' => 'Nuevo ticket',                                'en' => 'New ticket'],

        // Status filter
        'tickets.filter.all'                => ['es' => 'Todos',                                       'en' => 'All'],
        'tickets.filter.open'               => ['es' => 'Abiertos',                                    'en' => 'Open'],
        'tickets.filter.closed'             => ['es' => 'Cerrados',                                    'en' => 'Closed'],

        // Table headers
        'tickets.table.number'              => ['es' => 'N°',                                          'en' => 'N°'],
        'tickets.table.subject'             => ['es' => 'Asunto',                                      'en' => 'Subject'],
        'tickets.table.type'                => ['es' => 'Tipo',                                        'en' => 'Type'],
        'tickets.table.status'              => ['es' => 'Estado',                                      'en' => 'Status'],
        'tickets.table.from'                => ['es' => 'De',                                          'en' => 'From'],
        'tickets.table.assigned_to'         => ['es' => 'Asignado a',                                  'en' => 'Assigned to'],
        'tickets.table.replies'             => ['es' => 'Respuestas',                                  'en' => 'Replies'],
        'tickets.table.date'                => ['es' => 'Fecha',                                       'en' => 'Date'],
        'tickets.table.empty'               => ['es' => 'No hay tickets para mostrar.',               'en' => 'No tickets to display.'],
        'tickets.table.view'                => ['es' => 'Ver',                                         'en' => 'View'],

        // Types
        'tickets.type.bug'                  => ['es' => 'Bug',                                         'en' => 'Bug'],
        'tickets.type.data_discrepancy'     => ['es' => 'Discordancia',                                'en' => 'Discrepancy'],
        'tickets.type.support'              => ['es' => 'Soporte',                                     'en' => 'Support'],
        'tickets.type.bug_full'             => ['es' => 'Bug',                                         'en' => 'Bug'],
        'tickets.type.data_discrepancy_full'=> ['es' => 'Discordancia de datos',                       'en' => 'Data discrepancy'],

        // Statuses
        'tickets.status.open'               => ['es' => 'Abierto',                                     'en' => 'Open'],
        'tickets.status.closed'             => ['es' => 'Cerrado',                                     'en' => 'Closed'],

        // Create modal
        'tickets.modal.title'               => ['es' => 'Nuevo Ticket',                                'en' => 'New Ticket'],
        'tickets.modal.type_label'          => ['es' => 'Tipo de ticket',                              'en' => 'Ticket type'],
        'tickets.modal.subject_label'       => ['es' => 'Asunto',                                      'en' => 'Subject'],
        'tickets.modal.subject_placeholder' => ['es' => 'Describe brevemente el problema...',          'en' => 'Briefly describe the issue...'],
        'tickets.modal.message_label'       => ['es' => 'Descripción',                                 'en' => 'Description'],
        'tickets.modal.message_placeholder' => ['es' => 'Explica el problema con detalle...',          'en' => 'Explain the issue in detail...'],
        'tickets.modal.hint.discrepancy'    => ['es' => 'Este ticket será enviado al Leader de tu CP.','en' => 'This ticket will be sent to your CP Leader.'],
        'tickets.modal.hint.bug'            => ['es' => 'Este ticket será enviado al administrador.',  'en' => 'This ticket will be sent to the administrator.'],
        'tickets.modal.btn.cancel'          => ['es' => 'Cancelar',                                    'en' => 'Cancel'],
        'tickets.modal.btn.submit'          => ['es' => 'Enviar ticket',                               'en' => 'Send ticket'],

        // Show page
        'tickets.show.back'                 => ['es' => '← Volver a tickets',                         'en' => '← Back to tickets'],
        'tickets.show.from'                 => ['es' => 'De',                                          'en' => 'From'],
        'tickets.show.assigned_to'          => ['es' => 'Asignado a',                                  'en' => 'Assigned to'],
        'tickets.show.date'                 => ['es' => 'Fecha',                                       'en' => 'Date'],
        'tickets.show.closed_at'            => ['es' => 'Cerrado',                                     'en' => 'Closed'],
        'tickets.show.admin_assignee'       => ['es' => 'Administrador',                               'en' => 'Administrator'],
        'tickets.show.replies_title'        => ['es' => 'Respuestas',                                  'en' => 'Replies'],
        'tickets.show.reply_you'            => ['es' => '(tú)',                                        'en' => '(you)'],
        'tickets.show.reply_placeholder'    => ['es' => 'Escribe tu respuesta...',                     'en' => 'Write your reply...'],
        'tickets.show.reply_title'          => ['es' => 'Responder',                                   'en' => 'Reply'],
        'tickets.show.btn.reply'            => ['es' => 'Enviar respuesta',                            'en' => 'Send reply'],
        'tickets.show.btn.close'            => ['es' => 'Cerrar ticket',                               'en' => 'Close ticket'],
        'tickets.show.btn.reopen'           => ['es' => 'Reabrir',                                     'en' => 'Reopen'],
        'tickets.show.closed_message'       => ['es' => 'Este ticket está cerrado.',                   'en' => 'This ticket is closed.'],

        // Flash / toast messages
        'tickets.flash.created'             => ['es' => 'Ticket creado correctamente.',               'en' => 'Ticket created successfully.'],
        'tickets.flash.replied'             => ['es' => 'Respuesta enviada.',                          'en' => 'Reply sent.'],
        'tickets.flash.closed'              => ['es' => 'Ticket cerrado.',                             'en' => 'Ticket closed.'],
        'tickets.flash.reopened'            => ['es' => 'Ticket reabierto.',                           'en' => 'Ticket reopened.'],
        'tickets.flash.already_closed'      => ['es' => 'El ticket está cerrado.',                    'en' => 'The ticket is closed.'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                // Only insert if the key+language combo doesn't already exist
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
            ->delete();
    }
};
