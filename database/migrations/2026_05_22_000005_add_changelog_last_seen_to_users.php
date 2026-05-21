<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When the user last visited /changelog. Drives the pulsing badge
            // on the navbar — entries with published_at > this timestamp are
            // "unread" for that user.
            $table->timestamp('changelog_last_seen_at')->nullable()->after('language_preference');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('changelog_last_seen_at');
        });
    }
};
