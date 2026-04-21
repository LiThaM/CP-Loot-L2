<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
 
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignId('recipe_item_id')->nullable()->after('output_item_id')->constrained('items')->nullOnDelete();
        });
 
        // Attempt to link based on external_id and chronicle
        DB::statement("
            UPDATE recipes r
            SET r.recipe_item_id = (
                SELECT i.id 
                FROM items i 
                WHERE i.external_id = r.external_id 
                  AND i.chronicle = r.chronicle 
                  AND i.category = 'Recipe'
                LIMIT 1
            )
            WHERE r.recipe_item_id IS NULL
        ");
 
        // Fallback for those that don't match external_id but might match name
        DB::statement("
            UPDATE recipes r
            SET r.recipe_item_id = (
                SELECT i.id 
                FROM items i 
                WHERE i.name = r.name
                  AND i.chronicle = r.chronicle 
                  AND i.category = 'Recipe'
                LIMIT 1
            )
            WHERE r.recipe_item_id IS NULL
        ");
    }
 
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_item_id');
        });
    }
};
