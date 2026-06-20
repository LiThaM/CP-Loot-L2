<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_vault_auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_item_id')->constrained('clan_vault_items')->cascadeOnDelete();
            $table->unsignedInteger('min_bid')->default(0);
            $table->timestamp('ends_at');
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('winning_bid')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_vault_auctions');
    }
};
