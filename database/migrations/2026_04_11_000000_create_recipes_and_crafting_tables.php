<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('external_id');
            $table->string('chronicle', 20)->default('IL');
            $table->string('name');

            $table->foreignId('output_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->unsignedInteger('output_quantity')->default(1);

            $table->decimal('success_rate', 5, 2)->default(0);
            $table->unsignedInteger('mp_cost')->default(0);
            $table->unsignedInteger('adena_fee')->default(0);

            $table->string('icon_name')->nullable();
            $table->string('image_url')->nullable();
            $table->string('scraper_url')->nullable();

            $table->timestamps();

            $table->unique(['external_id', 'chronicle'], 'recipes_external_chronicle_unique');
            $table->index('chronicle');
            $table->index('name');
        });

        Schema::create('recipe_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['recipe_id', 'item_id'], 'recipe_materials_recipe_item_unique');
        });

        Schema::create('cp_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_id')->constrained('const_parties')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cp_id', 'recipe_id'], 'cp_recipes_cp_recipe_unique');
            $table->index(['cp_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_recipes');
        Schema::dropIfExists('recipe_materials');
        Schema::dropIfExists('recipes');
    }
};
