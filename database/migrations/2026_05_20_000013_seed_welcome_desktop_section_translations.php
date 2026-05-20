<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'welcome.hero.desktop_tip' => [
            'es' => '¿Juegas en Lu4? Te interesa también nuestra app de escritorio',
            'en' => 'Playing on Lu4? You may also want our desktop companion',
        ],
        'welcome.desktop.kicker' => [
            'es' => 'App de escritorio (opcional)',
            'en' => 'Desktop companion (optional)',
        ],
        'welcome.desktop.subtitle' => [
            'es' => 'AdenaLedgerStats es una herramienta independiente para Lu4. Captura loot y estadísticas del cliente del juego en tiempo real y, si lo deseas, las sincroniza con tu ledger en AdenaLedger. Tu CP puede usar AdenaLedger sin instalar nada.',
            'en' => 'AdenaLedgerStats is a separate tool for Lu4. It captures loot and stats from the game client in real time and, if you opt in, syncs them with your AdenaLedger ledger. Your CP can use AdenaLedger without installing anything.',
        ],
        'welcome.desktop.details' => [
            'es' => 'Ver detalles',
            'en' => 'See details',
        ],
        'welcome.desktop.note' => [
            'es' => 'Herramienta separada de AdenaLedger. No requiere cuenta para descargar.',
            'en' => 'Separate tool from AdenaLedger. No account required to download.',
        ],
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
