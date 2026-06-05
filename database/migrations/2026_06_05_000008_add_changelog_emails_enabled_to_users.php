<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'changelog_emails_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('changelog_emails_enabled')->default(true)->after('language_preference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'changelog_emails_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('changelog_emails_enabled');
            });
        }
    }
};
