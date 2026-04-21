<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('path');
            $table->string('method');
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index('user_id');
            $table->index('path');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
