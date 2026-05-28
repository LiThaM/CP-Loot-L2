<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hot paths in LootController::index filter loot_reports by
 * (cp_id, status, created_at) for the pending list and by
 * (cp_id, event_type, status) for the history tab. With CPs above ~5k
 * reports those queries were doing full table scans. Composite indexes
 * targeting the actual filter shapes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            $table->index(['cp_id', 'status', 'created_at'], 'loot_reports_cp_status_created_idx');
            $table->index(['cp_id', 'event_type', 'status'], 'loot_reports_cp_event_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            $table->dropIndex('loot_reports_cp_status_created_idx');
            $table->dropIndex('loot_reports_cp_event_status_idx');
        });
    }
};
