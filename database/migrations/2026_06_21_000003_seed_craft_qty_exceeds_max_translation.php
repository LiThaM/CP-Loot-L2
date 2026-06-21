<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['es', 'craft.qty.exceeds_max', 'Sin materiales suficientes para esa cantidad'],
            ['en', 'craft.qty.exceeds_max', 'Not enough materials for that quantity'],
            ['it', 'craft.qty.exceeds_max', 'Materiali insufficienti per quella quantità'],
            ['ru', 'craft.qty.exceeds_max', 'Недостаточно материалов для такого количества'],
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
