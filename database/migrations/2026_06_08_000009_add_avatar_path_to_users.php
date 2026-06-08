<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'avatar_path')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            // Relative path under storage/app/public, e.g.
            // "avatars/12/av_abc123.jpg". The accessor on the User model
            // turns it into an absolute URL via asset('storage/...').
            $table->string('avatar_path', 255)->nullable()->after('language_preference');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'avatar_path')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
