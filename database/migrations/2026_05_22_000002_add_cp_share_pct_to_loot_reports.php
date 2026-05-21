<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('cp_share_pct')->default(0)->after('adena_distribution');
        });

        // Map the legacy binary adena_distribution onto the new percentage
        // column so existing reports keep their behaviour: 'cp' = 100% to CP
        // fund, 'attendees' = 0% to CP fund, anything else stays 0%.
        DB::table('loot_reports')->where('adena_distribution', 'cp')->update(['cp_share_pct' => 100]);
        DB::table('loot_reports')->where('adena_distribution', 'attendees')->update(['cp_share_pct' => 0]);
    }

    public function down(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            $table->dropColumn('cp_share_pct');
        });
    }
};
