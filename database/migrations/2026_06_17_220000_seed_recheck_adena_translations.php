<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'warehouse.recheck.adena_title' => [
            'es' => 'Reconciliar adena',
            'en' => 'Reconcile adena',
        ],
        'warehouse.recheck.adena_hint' => [
            'es' => 'Si la adena registrada no cuadra (p.ej. comprasteis items sin registrar el gasto), pon aquí la adena real del almacén y se ajustará la diferencia.',
            'en' => "If the recorded adena doesn't match (e.g. items were bought without logging the spend), enter the real vault adena here and the difference is adjusted.",
        ],
        'warehouse.recheck.adena_recorded' => [
            'es' => 'Adena registrada',
            'en' => 'Recorded adena',
        ],
        'warehouse.recheck.adena_real' => [
            'es' => 'Adena real',
            'en' => 'Real adena',
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
        DB::table('translations')
            ->whereIn('key', array_keys($this->translations))
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
