<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against duplicate seeding if the migration runs twice against
        // the shared remote DB (local + server both pointing at it).
        if (DB::table('changelog_entries')->where('title_es', 'Delegación de gestión de miembros y código de invitación')->exists()) {
            return;
        }

        DB::table('changelog_entries')->insert([
            'type' => 'feature',
            'audience' => 'web',
            'title_es' => 'Delegación de gestión de miembros y código de invitación',
            'title_en' => 'Delegated member management and invite code',
            'title_it' => 'Delega della gestione dei membri e codice di invito',
            'title_ru' => 'Делегирование управления участниками и код приглашения',
            'body_es' => 'El líder fundador puede activar en [Configuración de la CP](/party?tab=settings) la opción **Permitir a co-líderes y accountants gestionar miembros**: con ella activa, tu staff ve el código de invitación en la cabecera de [/party](/party) y puede aprobar solicitudes pendientes desde el tab Miembros. Además, el fundador ahora puede **regenerar el código de invitación** si se ha filtrado — el enlace antiguo deja de funcionar al instante.',
            'body_en' => 'The founding leader can now enable **Allow co-leaders and accountants to manage members** in [CP Settings](/party?tab=settings): with it on, your staff sees the invite code in the [/party](/party) header and can approve pending requests from the Members tab. The founder can also **regenerate the invite code** if it leaked — the old link stops working instantly.',
            'body_it' => 'Il leader fondatore può attivare in [Impostazioni della CP](/party?tab=settings) l\'opzione **Consenti a co-leader e accountant di gestire i membri**: con l\'opzione attiva, il tuo staff vede il codice di invito nell\'intestazione di [/party](/party) e può approvare le richieste in sospeso dalla scheda Membri. Inoltre il fondatore ora può **rigenerare il codice di invito** se è trapelato — il vecchio link smette di funzionare all\'istante.',
            'body_ru' => 'Лидер-основатель теперь может включить в [настройках CP](/party?tab=settings) опцию **Разрешить со-лидерам и казначеям управлять участниками**: с ней ваш стафф видит код приглашения в шапке [/party](/party) и может одобрять ожидающие заявки во вкладке «Участники». Кроме того, основатель может **создать новый код приглашения**, если старый утёк — прежняя ссылка сразу перестаёт работать.',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_es', 'Delegación de gestión de miembros y código de invitación')
            ->delete();
    }
};
