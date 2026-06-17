<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The loot history renders each row's type via
     * `loot.event_types.{event_type}`. WAREHOUSE_RECHECK_LOSS and
     * WAREHOUSE_RECHECK_GAIN had no translation, so the list showed the raw
     * key (e.g. "LOOT.EVENT_TYPES.WAREHOUSE_RECHECK_LOSS"). Seed them.
     */
    public function up(): void
    {
        $now = now();
        $rows = [
            ['es', 'loot.event_types.warehouse_recheck_loss', 'Recuento (pérdida)'],
            ['en', 'loot.event_types.warehouse_recheck_loss', 'Recheck (loss)'],
            ['es', 'loot.event_types.warehouse_recheck_gain', 'Recuento (ganancia)'],
            ['en', 'loot.event_types.warehouse_recheck_gain', 'Recheck (gain)'],
        ];

        foreach ($rows as [$lang, $key, $value]) {
            DB::table('translations')->updateOrInsert(
                ['language' => $lang, 'key' => $key],
                ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('translations')
            ->whereIn('key', [
                'loot.event_types.warehouse_recheck_loss',
                'loot.event_types.warehouse_recheck_gain',
            ])
            ->delete();
    }
};
