<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anon_token_id')->nullable()
                ->constrained('anon_tokens')->nullOnDelete();
            $table->string('bot_version', 50);
            $table->string('os_version', 100)->nullable();
            $table->string('python_version', 50)->nullable();
            $table->string('fingerprint', 64)->index();
            $table->text('message')->nullable();
            $table->longText('stack_trace');
            $table->json('context_json')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamps();

            $table->index('reported_at');
            $table->index(['fingerprint', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_reports');
    }
};
