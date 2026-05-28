<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cp_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_id')->unique()->constrained('const_parties')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('body');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_rules');
    }
};
