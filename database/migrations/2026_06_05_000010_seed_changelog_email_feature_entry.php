<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
A partir de ahora, cada vez que se publica una nueva feature recibes un **email** además del badge en la navegación. Solo los CP leaders reciben este aviso — los miembros lo siguen viendo dentro de la app al loguearse.

¿No quieres el email? Lo desactivas en [tu perfil](/profile) con un click.
MD;

        $bodyEn = <<<'MD'
From now on, whenever a new feature ships you'll get an **email** in addition to the in-app badge. Only CP leaders receive this — regular members still see it inside the app when they log in.

Don't want the email? Turn it off in [your profile](/profile) with one click.
MD;

        $now = now();

        // notified_at is pre-set so the first cron tick after deploy does NOT
        // email an announcement *about* the email feature — recipients will
        // see this entry the next time they open the app.
        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'New feature emails for CP leaders'],
            [
                'audience' => 'web',
                'title_es' => 'Avisos por email para CP leaders',
                'title_en' => 'New feature emails for CP leaders',
                'body_es' => $bodyEs,
                'body_en' => $bodyEn,
                'published_at' => $now,
                'notified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_en', 'New feature emails for CP leaders')
            ->delete();
    }
};
