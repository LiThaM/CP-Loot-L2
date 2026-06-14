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
            // When true, tracker points are rounded UP to whole numbers (no
            // decimals). Default false keeps the legacy 2-decimal points.
            $table->boolean('tracker_round_points_up')->default(false)->after('tracker_round_up_below_1000');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            $table->dropColumn('tracker_round_points_up');
        });
    }
};
