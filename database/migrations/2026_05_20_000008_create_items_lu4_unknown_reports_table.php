<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_lu4_unknown_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('anon_token_id')->constrained('anon_tokens')->cascadeOnDelete();
            $table->text('ocr_context')->nullable();
            $table->unsignedInteger('count_seen')->default(1);
            $table->enum('status', ['pending', 'spam', 'promoted', 'rejected'])->default('pending');
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamps();

            $table->index(['status', 'reported_at']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_lu4_unknown_reports');
    }
};
