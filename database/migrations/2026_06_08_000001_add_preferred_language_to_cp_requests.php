<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cp_requests', 'preferred_language')) {
            return;
        }
        Schema::table('cp_requests', function (Blueprint $table) {
            // Captured at request time so future transactional mails to
            // unregistered requesters (e.g. reminders about an unclaimed CP)
            // can come in the right language. For registered users we keep
            // using `users.language_preference`.
            $table->string('preferred_language', 10)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cp_requests', 'preferred_language')) {
            return;
        }
        Schema::table('cp_requests', function (Blueprint $table) {
            $table->dropColumn('preferred_language');
        });
    }
};
