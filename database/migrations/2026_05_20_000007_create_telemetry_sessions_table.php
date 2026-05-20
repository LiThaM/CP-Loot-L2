<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anon_token_id')->constrained('anon_tokens')->cascadeOnDelete();
            $table->char('country_code', 2)->nullable();
            $table->string('bot_version', 50);
            $table->string('os_version', 100)->nullable();
            $table->string('python_version', 50)->nullable();
            $table->unsignedInteger('session_duration_seconds')->default(0);
            $table->string('char_class', 50)->nullable();
            $table->unsignedSmallInteger('char_level')->nullable();
            $table->unsignedBigInteger('xp_per_hour')->default(0);
            $table->unsignedBigInteger('adena_per_hour')->default(0);
            $table->unsignedInteger('ss_per_hour')->default(0);
            $table->unsignedSmallInteger('deaths')->default(0);
            $table->unsignedSmallInteger('level_ups')->default(0);
            $table->json('top_items_json')->nullable();
            $table->string('ocr_engine', 50)->nullable();
            $table->float('ocr_avg_ms')->nullable();
            $table->float('ocr_p95_ms')->nullable();
            $table->unsignedInteger('ocr_errors')->nullable();
            $table->boolean('ocr_gpu_used')->nullable();
            $table->timestamps();

            $table->index(['char_class', 'char_level']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_sessions');
    }
};
