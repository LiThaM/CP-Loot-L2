<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'tickets.modal.attachments_label' => ['es' => 'Adjuntos',                                          'en' => 'Attachments'],
        'tickets.modal.attachments_hint'  => ['es' => 'máx. 5 archivos · imágenes o vídeos',             'en' => 'max 5 files · images or videos'],
        'tickets.modal.attachments_pick'  => ['es' => 'Añadir imágenes o vídeos',                         'en' => 'Add images or videos'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                $exists = DB::table('translations')
                    ->where('key', $key)
                    ->where('language', $lang)
                    ->exists();

                if (! $exists) {
                    $rows[] = [
                        'language'   => $lang,
                        'key'        => $key,
                        'value'      => $value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (! empty($rows)) {
            DB::table('translations')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('translations')
            ->whereIn('key', array_keys($this->translations))
            ->delete();
    }
};
