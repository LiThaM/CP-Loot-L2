<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('source', 16)->default('web')->after('status');
            $table->foreignId('anon_token_id')->nullable()->after('source')
                ->constrained('anon_tokens')->nullOnDelete();
            $table->string('tracking_token', 64)->unique()->nullable()->after('anon_token_id');
            $table->string('bot_context_path')->nullable()->after('tracking_token');

            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['source', 'status']);
            $table->dropForeign(['anon_token_id']);
            $table->dropColumn(['source', 'anon_token_id', 'tracking_token', 'bot_context_path']);
        });
    }
};
