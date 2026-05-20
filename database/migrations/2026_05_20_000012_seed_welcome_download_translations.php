<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'welcome.nav.download'           => ['es' => 'Descargar',                          'en' => 'Download'],
        'welcome.download.cta'           => ['es' => 'Descargar AdenaLedgerStats (Lu4)',   'en' => 'Download AdenaLedgerStats (Lu4)'],
        'welcome.download.coming_soon'   => ['es' => 'Próximamente disponible',            'en' => 'Coming soon'],
        'welcome.download.critical'      => ['es' => 'Crítica',                            'en' => 'Critical'],
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
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
