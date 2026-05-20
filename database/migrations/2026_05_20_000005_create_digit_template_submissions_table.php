<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digit_template_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anon_token_id')->constrained('anon_tokens')->cascadeOnDelete();
            $table->string('char', 5);
            $table->string('storage_path');
            $table->float('sharpness')->nullable();
            $table->unsignedSmallInteger('dim_w');
            $table->unsignedSmallInteger('dim_h');
            $table->unsignedInteger('original_size_bytes');
            $table->boolean('kept_for_training')->default(false);
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['anon_token_id', 'char']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digit_template_submissions');
    }
};
