<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Crear una CP es ahora un solo paso. Antes recibías un link mágico y tenías que registrarte en una segunda pantalla; el resultado eran CPs huérfanas si alguien abandonaba el flujo a medias. Ahora el formulario pide tus datos de cuenta junto con los datos de la CP, y al enviar te dejamos directamente en tu [dashboard](/dashboard) como líder de la CP recién creada.
MD;

        $bodyEn = <<<'MD'
Creating a CP is now a single step. The old flow sent you a magic link and asked you to register on a second screen — that left orphan CPs whenever someone walked away mid-funnel. The form now collects your account credentials alongside the CP details, and on submit lands you straight in your [dashboard](/dashboard) as the leader of the brand-new CP.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'One-step CP creation funnel'],
            [
                'audience' => 'web',
                'title_es' => 'Crear CP en un solo paso',
                'title_en' => 'One-step CP creation funnel',
                'body_es' => $bodyEs,
                'body_en' => $bodyEn,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_en', 'One-step CP creation funnel')
            ->delete();
    }
};
