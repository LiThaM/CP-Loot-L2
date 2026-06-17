<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Label for the new DONATION loot event type (the loot history builds the
     * i18n key from the event type). Without this the rows would render the
     * raw "LOOT.EVENT_TYPES.DONATION" key.
     */
    public function up(): void
    {
        $now = now();
        $rows = [
            ['es', 'loot.event_types.donation', 'Donación'],
            ['en', 'loot.event_types.donation', 'Donation'],
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
        DB::table('translations')->where('key', 'loot.event_types.donation')->delete();
    }
};
