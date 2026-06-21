<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['es', 'craft.qty.label', 'Cantidad'],
            ['en', 'craft.qty.label', 'Quantity'],
            ['it', 'craft.qty.label', 'Quantità'],
            ['ru', 'craft.qty.label', 'Количество'],
        ];

        foreach ($rows as [$lang, $key, $value]) {
            DB::table('translations')->updateOrInsert(
                ['language' => $lang, 'key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    public function down(): void {}
};
