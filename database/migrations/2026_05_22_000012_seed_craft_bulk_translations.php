<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'party.craft_bulk.nav'              => ['es' => 'Craft masivo',                                        'en' => 'Bulk craft'],
        'party.craft_bulk.title'            => ['es' => 'Calculadora de craft masivo',                         'en' => 'Bulk crafting calculator'],
        'party.craft_bulk.subtitle'         => [
            'es' => 'Selecciona varias recetas, indica cantidades y el sistema te dice qué tienes en el warehouse, qué te falta y qué sub-crafts hacer. Crónica: {chronicle}.',
            'en' => 'Pick several recipes with quantities — the system tells you what is in the warehouse, what is missing and what sub-crafts to run. Chronicle: {chronicle}.',
        ],

        'party.craft_bulk.left.title'       => ['es' => 'Recetas a craftear',                                 'en' => 'Recipes to craft'],
        'party.craft_bulk.left.search_ph'   => ['es' => 'Buscar receta (min 2 caracteres)…',                  'en' => 'Search recipe (2+ chars)…'],
        'party.craft_bulk.left.empty'       => ['es' => 'Añade al menos una receta arriba.',                  'en' => 'Add at least one recipe above.'],
        'party.craft_bulk.left.output_qty'  => ['es' => 'Produce {n} por craft',                              'en' => 'Produces {n} per craft'],
        'party.craft_bulk.left.calculate'   => ['es' => 'Calcular',                                            'en' => 'Calculate'],
        'party.craft_bulk.left.calculating' => ['es' => 'Calculando…',                                         'en' => 'Calculating…'],

        'party.craft_bulk.right.idle'           => ['es' => 'Añade recetas a la izquierda y pulsa Calcular para ver el plan.', 'en' => 'Add recipes on the left and press Calculate to see the plan.'],
        'party.craft_bulk.right.totals_title'   => ['es' => 'Materiales necesarios',                           'en' => 'Required materials'],
        'party.craft_bulk.right.materials'      => ['es' => 'materiales',                                      'en' => 'materials'],
        'party.craft_bulk.right.all_covered'    => ['es' => '¡Tu warehouse cubre todo! No falta nada.',        'en' => 'Your warehouse covers everything — nothing missing.'],
        'party.craft_bulk.right.col.material'   => ['es' => 'Material',                                        'en' => 'Material'],
        'party.craft_bulk.right.col.need'       => ['es' => 'Necesito',                                        'en' => 'Need'],
        'party.craft_bulk.right.col.have'       => ['es' => 'Tengo',                                           'en' => 'Have'],
        'party.craft_bulk.right.col.missing'    => ['es' => 'Falta',                                           'en' => 'Missing'],
        'party.craft_bulk.right.sub_crafts_title' => ['es' => 'Sub-crafts a ejecutar',                         'en' => 'Sub-crafts to run'],
        'party.craft_bulk.right.sub_crafts_hint'  => ['es' => 'Crafts intermedios que el sistema decidió hacer porque tu warehouse no cubre la demanda directamente.', 'en' => 'Intermediate crafts the planner decided to run because the warehouse cannot cover the demand directly.'],
        'party.craft_bulk.right.sub_craft_line'   => [
            'es' => 'Craftea {n}× (produce {produces}) para cubrir {missing} de {item}',
            'en' => 'Craft {n}× (produces {produces}) to cover {missing} of {item}',
        ],

        'party.craft_bulk.error.generic' => ['es' => 'No se pudo calcular el plan.',                            'en' => 'Could not calculate the plan.'],
        'common.clear' => ['es' => 'Limpiar', 'en' => 'Clear'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                $exists = DB::table('translations')->where('key', $key)->where('language', $lang)->exists();
                if (!$exists) {
                    $rows[] = ['language' => $lang, 'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        if (!empty($rows)) {
            DB::table('translations')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('translations')
            ->whereIn('key', array_keys($this->translations))
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
