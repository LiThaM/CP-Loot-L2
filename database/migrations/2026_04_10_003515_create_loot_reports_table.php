<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loot_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_id')->constrained('const_parties')->onDelete('cascade');
            $table->foreignId('requested_by_id')->constrained('users')->onDelete('cascade');
            $table->string('event_type'); // BOSS, EPIC, SIEGE, FARM
            $table->integer('points_per_member')->default(0);
            $table->string('status')->default('pending'); // pending, confirmed, rejected
            $table->string('image_proof')->nullable();
            $table->json('recipient_ids')->nullable(); // Attendants
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loot_reports');
    }
};
