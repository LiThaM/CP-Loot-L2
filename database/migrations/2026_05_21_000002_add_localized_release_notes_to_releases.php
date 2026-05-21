<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->text('release_notes_es')->nullable()->after('release_notes_md');
            $table->text('release_notes_en')->nullable()->after('release_notes_es');
        });

        // Backfill the localized columns from the legacy single-language field so
        // existing rows keep rendering on the public landing without a manual edit.
        DB::statement('UPDATE releases SET release_notes_es = release_notes_md WHERE release_notes_es IS NULL AND release_notes_md IS NOT NULL');
        DB::statement('UPDATE releases SET release_notes_en = release_notes_md WHERE release_notes_en IS NULL AND release_notes_md IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn(['release_notes_es', 'release_notes_en']);
        });
    }
};
