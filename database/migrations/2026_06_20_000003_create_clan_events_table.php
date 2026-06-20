<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->string('name');
            $table->enum('event_type', ['raid', 'epic_raid', 'siege', 'call_to_arms']);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->enum('status', ['scheduled', 'open', 'finalized'])->default('scheduled');
            $table->enum('outcome', ['killed', 'lost', 'split'])->nullable();
            $table->unsignedInteger('dkp_reward')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_events');
    }
};
