<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bug F de bugsApi/BUGS.md: POST /api/v1/app/crashes reusa esta
        // tabla (los crash_reports legacy del bot tienen bot_version; los
        // del cliente AdenaLedgerStats tienen app_version). Dedup por
        // (fingerprint, app_version) → contador de ocurrencias en vez de
        // filas repetidas.
        Schema::table('crash_reports', function (Blueprint $table) {
            $table->string('bot_version', 50)->nullable()->change();
            $table->string('app_version', 50)->nullable()->index();
            $table->string('char_name', 100)->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('client_ts')->nullable();

            $table->index(['fingerprint', 'app_version']);
        });
    }

    public function down(): void
    {
        Schema::table('crash_reports', function (Blueprint $table) {
            $table->dropIndex(['fingerprint', 'app_version']);
            $table->dropIndex(['app_version']);
            $table->dropColumn(['app_version', 'char_name', 'occurrences', 'last_seen_at', 'client_ts']);
        });
    }
};
