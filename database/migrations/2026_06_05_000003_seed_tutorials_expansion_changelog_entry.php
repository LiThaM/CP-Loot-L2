<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
El apartado [/tutoriales](/tutoriales) ahora cubre **todas las pantallas** con explicaciones detalladas. Antes había 4 bullets por rol; ahora cada pantalla (perfil, dashboard, reportar loot, CP Vault, warehouse personal, crafting, normas, aprobar reports, vender, ajustes, gestión de miembros, craft bulk, pagos externos…) tiene su propio bloque desplegable con texto y, cuando aplica, un tour interactivo de la pantalla real.
MD;

        $bodyEn = <<<'MD'
The [/tutoriales](/tutoriales) section now covers **every screen** with detailed explanations. Before there were 4 bullets per role; now every screen (profile, dashboard, reporting loot, CP Vault, personal warehouse, crafting, rules, approving reports, selling, settings, member management, craft bulk, external payouts…) has its own collapsible block with text and, when it applies, an interactive tour of the real screen.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Tutorials expanded: a block per screen'],
            [
                'audience' => 'web',
                'title_es' => 'Tutoriales ampliados: un bloque por pantalla',
                'title_en' => 'Tutorials expanded: a block per screen',
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
            ->where('title_en', 'Tutorials expanded: a block per screen')
            ->delete();
    }
};
