<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('loot_reports', 'description')) {
                $table->text('description')->nullable()->after('image_proof');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loot_reports', function (Blueprint $table) {
            if (Schema::hasColumn('loot_reports', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};

