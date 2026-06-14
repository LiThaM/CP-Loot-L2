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
            // When true, a loot entry worth under 1000 adena is valued at 1000
            // before converting to tracker points (round small drops up).
            // Default false preserves prior behaviour.
            $table->boolean('tracker_round_up_below_1000')->default(false)->after('tracker_value_by_market');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            $table->dropColumn('tracker_round_up_below_1000');
        });
    }
};
