<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l2_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 80);
            $table->string('race', 20);          // Human, Elf, Dark Elf, Orc, Dwarf, Kamael
            $table->string('class_type', 10);    // 1st, 2nd, 3rd
            $table->string('parent_code', 80)->nullable();
            $table->timestamps();

            $table->index('race');
            $table->index('class_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('l2_classes');
    }
};
