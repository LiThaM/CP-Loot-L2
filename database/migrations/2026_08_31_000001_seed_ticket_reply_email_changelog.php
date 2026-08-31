<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against duplicate seeding if the migration runs twice against
        // the shared remote DB (local + server both pointing at it).
        if (DB::table('changelog_entries')->where('title_es', 'Aviso por email cuando responden tu ticket')->exists()) {
            return;
        }

        DB::table('changelog_entries')->insert([
            'type' => 'feature',
            'audience' => 'web',
            'title_es' => 'Aviso por email cuando responden tu ticket',
            'title_en' => 'Email alert when someone replies to your ticket',
            'title_it' => 'Avviso via email quando qualcuno risponde al tuo ticket',
            'title_ru' => 'Уведомление по email об ответе на ваш тикет',
            'body_es' => 'Cuando el equipo de soporte (o el líder asignado) responda a uno de tus [tickets](/tickets), recibirás un email con la respuesta y un enlace para continuar la conversación.',
            'body_en' => 'When the support team (or the assigned leader) replies to one of your [tickets](/tickets), you\'ll get an email with the reply and a link to continue the conversation.',
            'body_it' => 'Quando il team di supporto (o il leader assegnato) risponde a uno dei tuoi [ticket](/tickets), riceverai un\'email con la risposta e un link per continuare la conversazione.',
            'body_ru' => 'Когда служба поддержки (или назначенный лидер) ответит на один из ваших [тикетов](/tickets), вы получите email с ответом и ссылкой для продолжения разговора.',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_es', 'Aviso por email cuando responden tu ticket')
            ->delete();
    }
};
