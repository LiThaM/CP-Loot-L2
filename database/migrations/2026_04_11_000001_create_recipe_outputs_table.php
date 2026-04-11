<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('chance', 8, 6)->nullable();
            $table->timestamps();

            $table->unique(['recipe_id', 'item_id'], 'recipe_outputs_recipe_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_outputs');
    }
};
