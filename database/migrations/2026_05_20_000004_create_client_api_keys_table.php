<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_hash', 64)->unique();
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->string('version_range')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('use_count')->default(0);
            $table->timestamps();

            $table->index(['active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_api_keys');
    }
};
