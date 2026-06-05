<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the generic "Features" block on the Welcome page with the
 * full /tutoriales topic accordion. Two-part seed:
 *
 * 1. New `welcome.tour.{kicker,heading,subtitle}` keys for the section
 *    header that introduces the topic accordion.
 * 2. Refresh of the three `welcome.section.how_it_works.steps.{1,2,3}`
 *    bullets — the previous copy was generic claims, not real steps
 *    in the user flow. New copy describes what actually happens:
 *    member reports → leader approves → vault + rules handle the rest.
 *
 * Idempotent — re-running just refreshes the rows.
 */
return new class extends Migration
{
    private array $translations = [
        // ---- New header for the topic accordion section ----
        'welcome.tour.kicker' => [
            'es' => 'Una vuelta completa',
            'en' => 'A full tour',
        ],
        'welcome.tour.heading' => [
            'es' => 'Lo que vas a encontrar dentro',
            'en' => "What you'll find inside",
        ],
        'welcome.tour.subtitle' => [
            'es' => 'Las pantallas reales que verás como miembro y como líder de CP. Expande cualquier bloque para entender qué hace cada zona — luego, al entrar, los tours interactivos te lo enseñan en vivo.',
            'en' => 'The actual screens you see as a member and as a CP leader. Expand any block to understand what each area does — once you log in, the interactive tours walk you through it live.',
        ],

        // ---- Refresh: real workflow, not value-prop bullets ----
        'welcome.section.how_it_works.steps.1.title' => [
            'es' => 'Reportas tu loot',
            'en' => 'You report your loot',
        ],
        'welcome.section.how_it_works.steps.1.text' => [
            'es' => 'Cualquier miembro abre un report en /loot con los attendees, los items y la captura. Queda pendiente hasta que el líder lo apruebe.',
            'en' => 'Any member opens a report in /loot with the attendees, items and screenshot. It stays pending until the leader approves it.',
        ],
        'welcome.section.how_it_works.steps.2.title' => [
            'es' => 'El líder lo aprueba',
            'en' => 'The leader approves',
        ],
        'welcome.section.how_it_works.steps.2.text' => [
            'es' => 'El líder confirma, ajusta el % al fondo de la CP y los puntos por evento. La adena se reparte sola entre los attendees presentes.',
            'en' => 'The leader confirms, tweaks the % to the CP fund and the event points. Adena splits automatically among the attendees on the report.',
        ],
        'welcome.section.how_it_works.steps.3.title' => [
            'es' => 'El warehouse hace el resto',
            'en' => 'The warehouse handles the rest',
        ],
        'welcome.section.how_it_works.steps.3.text' => [
            'es' => 'Items en el CP Vault, deudas en tu warehouse personal, normas versionadas que todos aceptan y audit log completo. Cada acción queda firmada y trazable.',
            'en' => 'Items in the CP Vault, debts in your personal warehouse, versioned rules everyone accepts, and a full audit log. Every action is signed and traceable.',
        ],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                DB::table('translations')->updateOrInsert(
                    ['language' => $lang, 'key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        // Only delete the truly new keys; the refreshed ones don't
        // get reverted to their old generic copy.
        DB::table('translations')
            ->whereIn('key', ['welcome.tour.kicker', 'welcome.tour.heading', 'welcome.tour.subtitle'])
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
