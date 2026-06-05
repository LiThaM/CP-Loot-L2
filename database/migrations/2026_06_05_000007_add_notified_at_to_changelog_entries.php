<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('changelog_entries', 'notified_at')) {
            Schema::table('changelog_entries', function (Blueprint $table) {
                $table->timestamp('notified_at')->nullable()->after('published_at');
                $table->index('notified_at');
            });
        }

        // Backfill: mark every existing row as already-notified so the first
        // cron tick after deploy does NOT email N months of historic entries
        // to every leader. New entries (notified_at NULL by default) will be
        // picked up normally.
        DB::table('changelog_entries')
            ->whereNull('notified_at')
            ->update(['notified_at' => DB::raw('published_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('changelog_entries', 'notified_at')) {
            Schema::table('changelog_entries', function (Blueprint $table) {
                $table->dropIndex(['notified_at']);
                $table->dropColumn('notified_at');
            });
        }
    }
};
