<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_cp_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignId('cp_id')->constrained('const_parties')->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'member'])->default('member');
            $table->boolean('can_approve_attendance')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // A CP can only belong to one clan
            $table->unique('cp_id');
            $table->unique(['clan_id', 'cp_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_cp_memberships');
    }
};
