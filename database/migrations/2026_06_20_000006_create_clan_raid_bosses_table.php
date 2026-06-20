<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_raid_bosses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('level')->nullable();
            $table->unsignedSmallInteger('respawn_hours')->default(4);
            $table->boolean('is_epic')->default(false);
            $table->enum('status', ['unknown', 'alive', 'killed'])->default('unknown');
            $table->timestamp('last_killed_at')->nullable();
            $table->timestamp('window_opens_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_raid_bosses');
    }
};
