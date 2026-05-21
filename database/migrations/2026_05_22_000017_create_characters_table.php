<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->foreignId('l2_class_id')->nullable()->constrained('l2_classes')->nullOnDelete();
            $table->string('race', 20)->nullable();
            $table->unsignedSmallInteger('level')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index('l2_class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
