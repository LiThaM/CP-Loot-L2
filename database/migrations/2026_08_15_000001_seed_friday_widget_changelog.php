<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against duplicate seeding if the migration runs twice against
        // the shared remote DB (local + server both pointing at it).
        if (DB::table('changelog_entries')->where('title_es', 'Nuevo asistente de soporte — pregúntale a Friday')->exists()) {
            return;
        }

        DB::table('changelog_entries')->insert([
            'type' => 'feature',
            'audience' => 'web',
            'title_es' => 'Nuevo asistente de soporte — pregúntale a Friday',
            'title_en' => 'New support assistant — ask Friday',
            'title_it' => 'Nuovo assistente di supporto — chiedi a Friday',
            'title_ru' => 'Новый ассистент поддержки — спросите Friday',
            'body_es' => 'Ahora hay un asistente de soporte en la esquina inferior derecha (solo en escritorio, para no chocar con el menú inferior en móvil). Pregúntale dudas sobre AdenaLedger al vuelo, sin esperar a un ticket.',
            'body_en' => "There's now a support assistant in the bottom-right corner (desktop only, so it doesn't collide with the mobile bottom-nav). Ask it quick questions about AdenaLedger without waiting on a ticket.",
            'body_it' => "Ora c'è un assistente di supporto nell'angolo in basso a destra (solo desktop, per non entrare in conflitto con il menu inferiore su mobile). Chiedigli al volo qualsiasi dubbio su AdenaLedger, senza aspettare un ticket.",
            'body_ru' => 'Теперь в правом нижнем углу есть ассистент поддержки (только на десктопе, чтобы не мешать нижнему меню на мобильных). Задавайте вопросы об AdenaLedger сразу, не дожидаясь тикета.',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_es', 'Nuevo asistente de soporte — pregúntale a Friday')
            ->delete();
    }
};
