<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Tab and section
        'cp.rules.tab'                       => ['es' => 'Normas',                                          'en' => 'Rules'],
        'cp.rules.title'                     => ['es' => 'Normas de la CP',                                'en' => 'CP rules'],
        'cp.rules.subtitle'                  => ['es' => 'Acuerdo interno que firma cada miembro.',         'en' => 'Internal agreement every member signs off.'],
        'cp.rules.empty_member'              => ['es' => 'El líder de tu CP aún no ha publicado normas.',  'en' => "Your CP leader hasn't published any rules yet."],
        'cp.rules.empty_leader_cta'          => ['es' => 'Publicar normas',                                'en' => 'Publish rules'],
        'cp.rules.edit_button'               => ['es' => 'Editar normas',                                  'en' => 'Edit rules'],
        'cp.rules.version_meta'              => ['es' => 'Versión {version} · actualizada el {date} por {author}', 'en' => 'Version {version} · updated on {date} by {author}'],
        'cp.rules.accepted_badge'            => ['es' => 'Aceptada',                                       'en' => 'Accepted'],
        'cp.rules.pending_badge'             => ['es' => 'Pendiente',                                      'en' => 'Pending'],

        // Editor modal
        'cp.rules.editor.title'              => ['es' => 'Editar normas de la CP',                          'en' => 'Edit CP rules'],
        'cp.rules.editor.placeholder'        => ['es' => "Escribe aquí las normas. Puedes usar **negritas**, *cursiva*, `código` y [enlaces](/loot). Cada salto de línea se respeta.", 'en' => 'Write your rules here. You can use **bold**, *italics*, `code` and [links](/loot). Line breaks are preserved.'],
        'cp.rules.editor.save'               => ['es' => 'Guardar y notificar a la CP',                     'en' => 'Save and notify the CP'],
        'cp.rules.editor.cancel'             => ['es' => 'Cancelar',                                        'en' => 'Cancel'],
        'cp.rules.editor.confirm_save_title' => ['es' => '¿Publicar nueva versión?',                        'en' => 'Publish new version?'],
        'cp.rules.editor.confirm_save_text'  => ['es' => 'Cada miembro de la CP tendrá que aceptar las normas de nuevo. ¿Continuar?', 'en' => 'Every CP member will have to accept the rules again. Continue?'],
        'cp.rules.editor.confirm_save_yes'   => ['es' => 'Sí, publicar',                                    'en' => 'Yes, publish'],
        'cp.rules.saved'                     => ['es' => 'Normas publicadas',                               'en' => 'Rules published'],

        // Blocking modal
        'cp.rules.modal.kicker'              => ['es' => 'Normas de la CP',                                'en' => 'CP rules'],
        'cp.rules.modal.title'               => ['es' => 'Lee y acepta antes de continuar',                 'en' => 'Read and accept to continue'],
        'cp.rules.modal.subtitle'            => ['es' => 'El líder ha publicado normas. Tienes que aceptarlas para seguir usando la app.', 'en' => 'The leader published rules. You must accept them to keep using the app.'],
        'cp.rules.modal.accept'              => ['es' => 'Acepto las normas',                              'en' => 'I accept the rules'],
        'cp.rules.modal.no_dismiss_hint'     => ['es' => 'Si no aceptas, no podrás operar dentro de la CP.', 'en' => 'If you do not accept, you cannot operate inside the CP.'],
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
        DB::table('translations')
            ->whereIn('key', array_keys($this->translations))
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
