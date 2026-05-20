<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anon_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_uuid')->unique();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->unsignedBigInteger('request_count')->default(0);
            $table->char('country_code_last', 2)->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->string('banned_reason')->nullable();
            $table->timestamps();

            $table->index('last_seen_at');
            $table->index('banned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anon_tokens');
    }
};
