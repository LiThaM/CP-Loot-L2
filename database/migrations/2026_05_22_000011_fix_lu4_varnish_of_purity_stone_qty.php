<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The LU4 "Recipe: Varnish of Purity" was seeded with
        // Stone of Purity x1, but the real LU4 server consumes 5 per
        // Varnish of Purity. Without this fix the recipe explorer
        // under-reported how many SoP you need for any item that
        // chains through Varnish of Purity (Maestro Mold → Blacksmith's
        // Frame → Varnish of Purity → Stone of Purity).
        $recipe = DB::table('recipes')
            ->where('chronicle', 'LU4')
            ->where('name', 'Recipe: Varnish of Purity')
            ->first();

        if (!$recipe) {
            return;
        }

        $stoneId = DB::table('items')
            ->whereRaw('LOWER(name) = ?', ['stone of purity'])
            ->where('chronicle', 'LU4')
            ->value('id');

        if (!$stoneId) {
            return;
        }

        DB::table('recipe_materials')
            ->where('recipe_id', $recipe->id)
            ->where('item_id', $stoneId)
            ->update(['quantity' => 5]);
    }

    public function down(): void
    {
        $recipe = DB::table('recipes')
            ->where('chronicle', 'LU4')
            ->where('name', 'Recipe: Varnish of Purity')
            ->first();
        if (!$recipe) {
            return;
        }
        $stoneId = DB::table('items')
            ->whereRaw('LOWER(name) = ?', ['stone of purity'])
            ->where('chronicle', 'LU4')
            ->value('id');
        if (!$stoneId) {
            return;
        }
        DB::table('recipe_materials')
            ->where('recipe_id', $recipe->id)
            ->where('item_id', $stoneId)
            ->update(['quantity' => 1]);
    }
};
