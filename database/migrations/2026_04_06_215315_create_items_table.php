<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('grade')->nullable(); // NG, D, C, B, A, S
            $table->string('category')->nullable(); // Weapon, Armor, Jewelry, Material, Recipe, Drop
            $table->string('image_url')->nullable();
            $table->integer('base_points')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
