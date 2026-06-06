<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tracker_contributions')) {
            return;
        }

        Schema::create('tracker_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_id')->constrained('const_parties')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // 'material' = derived from loot entry, 'event' = manual leader bonus.
            $table->string('type', 16);
            $table->decimal('points', 18, 2);
            $table->string('description', 255);
            // SOLO, EVENT, or PARTY/N (where N is the attendee count at derive time).
            $table->string('badge', 20);
            $table->foreignId('source_loot_entry_id')->nullable()->constrained('loot_entries')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['cp_id', 'user_id']);
            $table->index(['cp_id', 'created_at']);
            // Idempotency for auto-derive: a given loot entry can grant
            // points to a user only once, even if the loot report is
            // re-confirmed by accident.
            $table->unique(['source_loot_entry_id', 'user_id'], 'tracker_contrib_unique_per_loot_entry_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_contributions');
    }
};
