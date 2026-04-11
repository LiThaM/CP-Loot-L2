<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            $table->string('adena_distribution')->nullable()->after('recipient_ids'); // 'attendees' | 'cp'
        });
    }

    public function down(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            $table->dropColumn('adena_distribution');
        });
    }
};
