<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Optional details for the user's MAIN character. The nick is
            // already users.name; these columns just enrich the main with
            // class / race / level so leaders can see it next to the user
            // in the loot modal without forcing them to create a separate
            // `characters` row for the primary char.
            $table->foreignId('main_class_id')
                ->nullable()
                ->after('language_preference')
                ->constrained('l2_classes')
                ->nullOnDelete();
            $table->string('main_race', 20)->nullable()->after('main_class_id');
            $table->unsignedSmallInteger('main_level')->nullable()->after('main_race');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['main_class_id']);
            $table->dropColumn(['main_class_id', 'main_race', 'main_level']);
        });
    }
};
