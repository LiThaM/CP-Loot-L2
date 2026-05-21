<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digit_template_submissions', function (Blueprint $table) {
            // Perceptual hash (aHash, 64 bits → 16 hex chars). Indexed because
            // the consensus algorithm groups by Hamming distance on this column.
            $table->string('phash', 16)->nullable()->after('storage_path');
            $table->string('bot_version', 50)->nullable()->after('phash');

            $table->index(['char', 'phash']);
            $table->index('bot_version');
        });
    }

    public function down(): void
    {
        Schema::table('digit_template_submissions', function (Blueprint $table) {
            $table->dropIndex(['char', 'phash']);
            $table->dropIndex(['bot_version']);
            $table->dropColumn(['phash', 'bot_version']);
        });
    }
};
