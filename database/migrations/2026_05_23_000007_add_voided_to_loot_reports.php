<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('loot_reports', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('loot_reports', 'voided_by_user_id')) {
                $table->foreignId('voided_by_user_id')->nullable()->after('voided_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('loot_reports', 'voided_reason')) {
                $table->string('voided_reason', 255)->nullable()->after('voided_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            if (Schema::hasColumn('loot_reports', 'voided_by_user_id')) {
                $table->dropForeign(['voided_by_user_id']);
                $table->dropColumn('voided_by_user_id');
            }
            if (Schema::hasColumn('loot_reports', 'voided_reason')) {
                $table->dropColumn('voided_reason');
            }
            if (Schema::hasColumn('loot_reports', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
        });
    }
};
