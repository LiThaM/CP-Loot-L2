<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cp_auctions')) {
            return;
        }

        Schema::create('cp_auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_id')->constrained('const_parties')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->unsignedInteger('amount');
            // 'points' (DKP tracker) or 'adena'. Picked by the leader on
            // open() — different auctions in the same CP can use different
            // currencies. `points` requires the CP to have tracker_enabled.
            $table->string('currency', 8);
            $table->decimal('starting_bid', 18, 2);
            $table->decimal('buy_now_price', 18, 2)->nullable();
            $table->decimal('current_bid', 18, 2)->nullable();
            $table->foreignId('current_bidder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ends_at');
            // open → closed (cron set winner) → fulfilled (leader handed item)
            // open → cancelled (leader cancels OR cron with no bidder).
            $table->string('status', 16)->default('open');
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            // Source LootReport that "removed" the item from the warehouse
            // as a reservation (event_type='WAREHOUSE_RECHECK_LOSS'). When
            // a cancel happens we generate the opposite WAREHOUSE_RECHECK_GAIN
            // to return stock; fulfill leaves it consumed.
            $table->foreignId('reservation_report_id')->nullable()->constrained('loot_reports')->nullOnDelete();
            $table->timestamps();

            $table->index(['cp_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_auctions');
    }
};
