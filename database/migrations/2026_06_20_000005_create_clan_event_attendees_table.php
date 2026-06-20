<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_event_id')->constrained('clan_events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_name')->nullable();
            $table->foreignId('cp_id')->nullable()->constrained('const_parties')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One attendance record per user per event
            $table->unique(['clan_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_event_attendees');
    }
};
