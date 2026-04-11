<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('external_id')->nullable()->after('id');
            $table->string('chronicle', 20)->default('IL')->after('external_id');
            $table->string('source', 30)->default('manual')->after('chronicle'); // elmore, masterwork, manual
            $table->string('icon_name')->nullable()->after('image_url');        // value field from API (e.g. weapon_small_sword_i00)
            $table->string('description')->nullable()->after('base_points');

            // Unique constraint: same external_id + chronicle = same item
            $table->unique(['external_id', 'chronicle'], 'items_external_chronicle_unique');
            $table->index('chronicle');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique('items_external_chronicle_unique');
            $table->dropIndex(['chronicle']);
            $table->dropIndex(['source']);
            $table->dropColumn(['external_id', 'chronicle', 'source', 'icon_name', 'description']);
        });
    }
};
