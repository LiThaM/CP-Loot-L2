<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'profile.chars.title'             => ['es' => 'Personajes L2',                       'en' => 'L2 characters'],
        'profile.chars.main_label'        => ['es' => 'Personaje principal',                 'en' => 'Main character'],
        'profile.chars.badge_main'        => ['es' => 'principal',                            'en' => 'main'],
        'profile.chars.secondaries_title' => ['es' => 'Personajes secundarios',              'en' => 'Secondary characters'],
        'profile.chars.add'               => ['es' => 'Añadir personaje',                    'en' => 'Add character'],
        'profile.chars.empty'             => ['es' => 'Aún no tienes personajes secundarios.', 'en' => 'No secondary characters yet.'],

        'profile.chars.field.nick'  => ['es' => 'Nick',     'en' => 'Nick'],
        'profile.chars.field.race'  => ['es' => 'Raza',     'en' => 'Race'],
        'profile.chars.field.class' => ['es' => 'Clase',    'en' => 'Class'],
        'profile.chars.field.level' => ['es' => 'Nivel',    'en' => 'Level'],

        'profile.chars.modal.create' => ['es' => 'Añadir personaje',  'en' => 'Add character'],
        'profile.chars.modal.edit'   => ['es' => 'Editar personaje',  'en' => 'Edit character'],

        'profile.chars.confirm.delete_title' => ['es' => '¿Eliminar personaje?', 'en' => 'Delete character?'],
        'profile.chars.confirm.delete_text'  => ['es' => 'Se eliminará "{name}". Las menciones en farms históricas conservarán su histórico.', 'en' => '"{name}" will be removed. Historic farm mentions keep their record.'],
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
