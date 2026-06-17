<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly objectives = items the CP is hunting for. Each objective has a
     * target quantity and a points multiplier. On CPs with the tracker, any
     * loot/donation of an objective item awards `points × multiplier` until
     * the target is reached (then `completed_at` is stamped and it reverts to
     * normal). Without the tracker the objectives are informational only.
     */
    public function up(): void
    {
        if (Schema::hasTable('cp_weekly_objectives')) {
            return;
        }

        Schema::create('cp_weekly_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_id')->constrained('const_parties')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->unsignedInteger('target_quantity');
            $table->decimal('multiplier', 5, 2)->default(1.00);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cp_id', 'completed_at']);
            $table->index(['cp_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_weekly_objectives');
    }
};
