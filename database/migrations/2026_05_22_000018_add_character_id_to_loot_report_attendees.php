<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_report_attendees', function (Blueprint $table) {
            $table->foreignId('character_id')
                ->nullable()
                ->after('user_id')
                ->constrained('characters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loot_report_attendees', function (Blueprint $table) {
            $table->dropForeign(['character_id']);
            $table->dropColumn('character_id');
        });
    }
};
