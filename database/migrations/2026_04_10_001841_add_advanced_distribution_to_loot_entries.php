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
        Schema::table('loot_entries', function (Blueprint $table) {
            $table->enum('distribution_type', ['individual', 'full_split', 'partial_split'])->default('individual')->after('status');
            $table->json('recipient_ids')->nullable()->after('distribution_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loot_entries', function (Blueprint $table) {
            $table->dropColumn(['distribution_type', 'recipient_ids']);
        });
    }
};
