<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'warehouse.recheck.adena_spent' => [
            'es' => 'Gastada',
            'en' => 'Spent',
        ],
        // Refreshed to mention the "spent" shortcut.
        'warehouse.recheck.adena_hint' => [
            'es' => 'Pon la adena real del almacén, o cuánto se gastó (admite atajos como 5kk = 5.000.000). Se ajustará la diferencia. Útil si comprasteis items sin registrar el gasto.',
            'en' => 'Enter the real vault adena, or how much was spent (accepts shortcuts like 5kk = 5,000,000). The difference is adjusted. Handy when items were bought without logging the spend.',
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
            ->where('key', 'warehouse.recheck.adena_spent')
            ->whereIn('language', ['es', 'en'])
            ->delete();
        // adena_hint is left as-is on rollback (its prior value isn't restored).
    }
};
