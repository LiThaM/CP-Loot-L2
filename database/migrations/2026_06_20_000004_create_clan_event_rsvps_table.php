<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_event_id')->constrained('clan_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('response', ['going', 'not_going'])->default('going');
            $table->timestamps();

            $table->unique(['clan_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_event_rsvps');
    }
};
