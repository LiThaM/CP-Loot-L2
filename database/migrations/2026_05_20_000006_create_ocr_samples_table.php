<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anon_token_id')->constrained('anon_tokens')->cascadeOnDelete();
            $table->enum('category', ['bar', 'chat', 'chat_damage', 'system_msg', 'bar_misread']);
            $table->string('storage_path');
            $table->string('image_hash_sha256', 64)->unique();
            $table->string('ground_truth', 100);
            $table->string('expected_value', 100)->nullable();
            $table->string('actual_ocr', 100)->nullable();
            $table->float('confidence')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['category', 'created_at']);
            $table->index('anon_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_samples');
    }
};
