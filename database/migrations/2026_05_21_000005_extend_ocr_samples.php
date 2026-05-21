<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocr_samples', function (Blueprint $table) {
            $table->string('bot_version', 50)->nullable()->after('confidence');
            $table->string('status', 16)->default('pending')->after('bot_version');
            $table->timestamp('reviewed_at')->nullable()->after('status');

            $table->index('status');
            $table->index('bot_version');
        });

        // Drop the original ENUM/CHECK on `category` by converting to plain
        // VARCHAR — the new spec adds `level` and future categories will keep
        // landing here. Enforced application-side via OcrSample::CATEGORIES.
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite cannot ALTER a CHECK constraint in place; rebuild the
            // table via raw SQL so existing rows are preserved.
            DB::statement('CREATE TABLE ocr_samples_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                anon_token_id INTEGER NOT NULL,
                category VARCHAR(40) NOT NULL,
                storage_path VARCHAR(255) NOT NULL,
                image_hash_sha256 VARCHAR(64) NOT NULL UNIQUE,
                ground_truth VARCHAR(500) NULL,
                expected_value VARCHAR(500) NULL,
                actual_ocr VARCHAR(500) NULL,
                confidence FLOAT NULL,
                bot_version VARCHAR(50) NULL,
                status VARCHAR(16) NOT NULL DEFAULT "pending",
                reviewed_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY(anon_token_id) REFERENCES anon_tokens(id) ON DELETE CASCADE
            )');
            DB::statement('INSERT INTO ocr_samples_new SELECT id, anon_token_id, category, storage_path, image_hash_sha256, ground_truth, expected_value, actual_ocr, confidence, bot_version, status, reviewed_at, created_at, updated_at FROM ocr_samples');
            DB::statement('DROP TABLE ocr_samples');
            DB::statement('ALTER TABLE ocr_samples_new RENAME TO ocr_samples');
            DB::statement('CREATE INDEX ocr_samples_category_created_at_index ON ocr_samples(category, created_at)');
            DB::statement('CREATE INDEX ocr_samples_anon_token_id_index ON ocr_samples(anon_token_id)');
            DB::statement('CREATE INDEX ocr_samples_status_index ON ocr_samples(status)');
            DB::statement('CREATE INDEX ocr_samples_bot_version_index ON ocr_samples(bot_version)');
        } else {
            DB::statement('ALTER TABLE ocr_samples MODIFY category VARCHAR(40) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('ocr_samples', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['bot_version']);
            $table->dropColumn(['bot_version', 'status', 'reviewed_at']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ocr_samples MODIFY category ENUM('bar','chat','chat_damage','system_msg','bar_misread') NOT NULL");
        }
    }
};
