<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite ya quedó en VARCHAR(500) tras la rebuild de la tabla en
        // 2026_05_21_000005_extend_ocr_samples.php. MySQL se quedó atrás
        // con el VARCHAR(100) original, lo que provoca SQLSTATE[22001]
        // cuando el cliente sube ocr_text/ground_truth largos.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ocr_samples
                MODIFY ground_truth   VARCHAR(500) NULL,
                MODIFY expected_value VARCHAR(500) NULL,
                MODIFY actual_ocr     VARCHAR(500) NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ocr_samples
                MODIFY ground_truth   VARCHAR(100) NOT NULL,
                MODIFY expected_value VARCHAR(100) NULL,
                MODIFY actual_ocr     VARCHAR(100) NULL');
        }
    }
};
