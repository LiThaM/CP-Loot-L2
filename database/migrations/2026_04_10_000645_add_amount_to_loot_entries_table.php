<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('amount')->default(1)->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('loot_entries', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
