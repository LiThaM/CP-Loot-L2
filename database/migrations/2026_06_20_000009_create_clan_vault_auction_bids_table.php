<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_vault_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('clan_vault_auctions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('bid_amount');
            $table->timestamps();

            // One bid record per user per auction — updated on re-bid
            $table->unique(['auction_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_vault_auction_bids');
    }
};
