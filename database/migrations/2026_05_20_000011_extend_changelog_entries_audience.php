<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('changelog_entries', function (Blueprint $table) {
            $table->string('audience', 16)->default('both')->after('type');
            $table->foreignId('release_id')->nullable()->after('audience')
                ->constrained('releases')->nullOnDelete();

            $table->index(['audience', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('changelog_entries', function (Blueprint $table) {
            $table->dropIndex(['audience', 'published_at']);
            $table->dropForeign(['release_id']);
            $table->dropColumn(['audience', 'release_id']);
        });
    }
};
