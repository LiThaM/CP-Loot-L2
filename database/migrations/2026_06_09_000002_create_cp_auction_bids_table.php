<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cp_auction_bids')) {
            return;
        }

        Schema::create('cp_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('cp_auctions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('amount', 18, 2);
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->index(['auction_id', 'placed_at']);
            $table->index(['user_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_auction_bids');
    }
};
