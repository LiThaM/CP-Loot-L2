<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * key => [es, en, it, ru]
     */
    private array $translations = [
        'cp.settings.staff_manage_members' => [
            'es' => 'Permitir a co-líderes y accountants gestionar miembros',
            'en' => 'Allow co-leaders and accountants to manage members',
            'it' => 'Consenti a co-leader e accountant di gestire i membri',
            'ru' => 'Разрешить со-лидерам и казначеям управлять участниками',
        ],
        'cp.settings.staff_manage_members_hint' => [
            'es' => 'Con esta opción activa, los miembros con rol CP Leader o Accountant ven el código de invitación y pueden aprobar solicitudes pendientes. Regenerar el código sigue siendo exclusivo del fundador.',
            'en' => 'When enabled, members with the CP Leader or Accountant role can see the invite code and approve pending requests. Regenerating the code remains founder-only.',
            'it' => 'Se attiva, i membri con ruolo CP Leader o Accountant vedono il codice di invito e possono approvare le richieste in sospeso. Rigenerare il codice resta riservato al fondatore.',
            'ru' => 'Если включено, участники с ролью CP Leader или Accountant видят код приглашения и могут одобрять ожидающие заявки. Создание нового кода остаётся только у основателя.',
        ],
        'party.invite.regenerate_btn' => [
            'es' => 'Regenerar código',
            'en' => 'Regenerate code',
            'it' => 'Rigenera codice',
            'ru' => 'Создать новый код',
        ],
        'party.invite.regenerate_confirm_title' => [
            'es' => '¿Regenerar código de invitación?',
            'en' => 'Regenerate invite code?',
            'it' => 'Rigenerare il codice di invito?',
            'ru' => 'Создать новый код приглашения?',
        ],
        'party.invite.regenerate_confirm_body' => [
            'es' => 'El código actual dejará de funcionar inmediatamente. Los enlaces de invitación ya compartidos quedarán inválidos.',
            'en' => 'The current code will stop working immediately. Invite links you already shared will become invalid.',
            'it' => 'Il codice attuale smetterà di funzionare immediatamente. I link di invito già condivisi non saranno più validi.',
            'ru' => 'Текущий код сразу перестанет работать. Уже отправленные ссылки-приглашения станут недействительными.',
        ],
        'party.invite.regenerated' => [
            'es' => 'Código de invitación regenerado.',
            'en' => 'Invite code regenerated.',
            'it' => 'Codice di invito rigenerato.',
            'ru' => 'Код приглашения обновлён.',
        ],
        'tutorials.topic.cp_settings.bullet.5' => [
            'es' => '**Delegar gestión de miembros**: activa «Permitir a co-líderes y accountants gestionar miembros» para que tu staff vea el código de invitación y apruebe solicitudes pendientes. Regenerar el código sigue siendo solo del fundador.',
            'en' => '**Delegate member management**: enable "Allow co-leaders and accountants to manage members" so your staff can see the invite code and approve pending requests. Regenerating the code stays founder-only.',
            'it' => '**Delega la gestione dei membri**: attiva «Consenti a co-leader e accountant di gestire i membri» perché il tuo staff veda il codice di invito e approvi le richieste in sospeso. Rigenerare il codice resta solo del fondatore.',
            'ru' => '**Делегируйте управление участниками**: включите «Разрешить со-лидерам и казначеям управлять участниками», чтобы ваш стафф видел код приглашения и одобрял ожидающие заявки. Новый код может создать только основатель.',
        ],
        'tutorials.topic.members_mgmt.bullet.6' => [
            'es' => '**Aprobación por staff**: si el fundador activa la opción en Configuración, los co-líderes y accountants también pueden aprobar miembros en espera desde el tab Miembros.',
            'en' => '**Staff approvals**: if the founder enables the option in Settings, co-leaders and accountants can also approve waiting members from the Members tab.',
            'it' => '**Approvazioni dello staff**: se il fondatore attiva l\'opzione nelle Impostazioni, anche i co-leader e gli accountant possono approvare i membri in attesa dalla scheda Membri.',
            'ru' => '**Одобрение стаффом**: если основатель включит опцию в настройках, со-лидеры и казначеи тоже смогут одобрять ожидающих участников во вкладке «Участники».',
        ],
    ];

    public function up(): void
    {
        foreach ($this->translations as $key => $values) {
            foreach ($values as $language => $value) {
                DB::table('translations')->updateOrInsert(
                    ['language' => $language, 'key' => $key],
                    ['value' => $value, 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('translations')->whereIn('key', array_keys($this->translations))->delete();
    }
};
