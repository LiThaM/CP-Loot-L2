<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('version')->unique();
            $table->enum('channel', ['stable', 'beta'])->default('stable');
            $table->string('storage_path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('release_notes_md')->nullable();
            $table->boolean('critical_update')->default(false);
            $table->string('min_supported_version')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['channel', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
