<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Changelog entries were bilingual (es/en) columns. Add Italian and
     * Russian so the changelog can be shown/emailed in those languages too;
     * the frontend/mail fall back to English when a column is null.
     */
    public function up(): void
    {
        Schema::table('changelog_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('changelog_entries', 'title_it')) {
                $table->string('title_it')->nullable()->after('body_en');
            }
            if (! Schema::hasColumn('changelog_entries', 'title_ru')) {
                $table->string('title_ru')->nullable()->after('title_it');
            }
            if (! Schema::hasColumn('changelog_entries', 'body_it')) {
                $table->text('body_it')->nullable()->after('title_ru');
            }
            if (! Schema::hasColumn('changelog_entries', 'body_ru')) {
                $table->text('body_ru')->nullable()->after('body_it');
            }
        });
    }

    public function down(): void
    {
        Schema::table('changelog_entries', function (Blueprint $table) {
            $table->dropColumn(['title_it', 'title_ru', 'body_it', 'body_ru']);
        });
    }
};
