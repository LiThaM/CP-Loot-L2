<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Hemos reorganizado el menú principal: las 11 entradas de antes se agrupan ahora en **4 desplegables temáticos** (CP / Loot / Craft / Más). El menú admin pasa de 8 a 3 (Manage / System). Las rutas no cambian — tus bookmarks siguen funcionando, solo cambia cómo se llegan desde la nav.

**Nueva pantalla**: [/party/stats](/party/stats) — un deep-dive de tu CP con KPIs, gráficos de tendencia de reports, flujo de adena, items más dropeados, mapa de actividad por miembro, distribución del vault por grade, scoreboard financiero y top del DKP tracker. Selector de periodo 7/30/90 días. Accesible a todos los miembros del CP.

En móvil la barra inferior pasa de 7-8 iconos a 5 (Home / CP / Report / Loot / Profile) — los menos usados (Items DB, Tickets, Tutoriales) se acceden desde el menú del avatar.
MD;

        $bodyEn = <<<'MD'
We've reorganized the main navigation: the previous 11 links are now grouped into **4 thematic dropdowns** (CP / Loot / Craft / More). Admin nav goes from 8 to 3 (Manage / System). Routes are unchanged — your bookmarks still work, only the path through the menu changes.

**New page**: [/party/stats](/party/stats) — a deep-dive of your CP with KPIs, report-trend charts by event type, adena flow (in vs out), top items dropped, member activity heatmap, vault distribution by grade, financial scoreboard and DKP tracker top-5. Period selector 7/30/90 days. Available to every member of the CP.

On mobile, the bottom-nav goes from 7-8 icons to 5 (Home / CP / Report / Loot / Profile) — less-used links (Items DB, Tickets, Tutorials) live inside the avatar menu now.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Nav restructure + CP stats deep-dive'],
            [
                'audience' => 'web',
                'title_es' => 'Reorganización del menú + estadísticas avanzadas de CP',
                'title_en' => 'Nav restructure + CP stats deep-dive',
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
            ->where('title_en', 'Nav restructure + CP stats deep-dive')
            ->delete();
    }
};
