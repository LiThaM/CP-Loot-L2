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
        Schema::table('const_parties', function (Blueprint $table) {
            // When true the value tracker prices loot by market_price (NPC sell
            // price as fallback); when false it prices by NPC sell price
            // (market_price as fallback). Default true preserves prior behaviour.
            $table->boolean('tracker_value_by_market')->default(true)->after('tracker_divisor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            $table->dropColumn('tracker_value_by_market');
        });
    }
};
