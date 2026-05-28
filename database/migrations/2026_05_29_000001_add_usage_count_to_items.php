<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `items.usage_count` powers the autocomplete ranking — items that
 * actually appear in loot reports / wishlists / recipes float to the
 * top of search results so junk items don't bury the useful ones.
 *
 * Backfill is computed here once. A scheduled artisan command
 * (items:recompute-usage) re-runs the aggregate daily to keep it
 * fresh; we don't bother with realtime counter-cache because the
 * search-ranking use case tolerates 24h staleness.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guard: column may already exist if a previous run
        // half-applied. The backfill below is safe to re-run anyway.
        if (! Schema::hasColumn('items', 'usage_count')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedInteger('usage_count')->default(0)->after('market_price');
                $table->index('usage_count');
            });
        }

        // Backfill via correlated subqueries — portable across MySQL
        // and SQLite (tests). Each FK source contributes a COUNT;
        // COALESCE turns missing matches into 0.
        DB::statement(<<<'SQL'
            UPDATE items
            SET usage_count =
                COALESCE((SELECT COUNT(*) FROM loot_entries WHERE loot_entries.item_id = items.id), 0)
              + COALESCE((SELECT COUNT(*) FROM wishlists WHERE wishlists.item_id = items.id), 0)
              + COALESCE((SELECT COUNT(*) FROM recipe_materials WHERE recipe_materials.item_id = items.id), 0)
              + COALESCE((SELECT COUNT(*) FROM recipe_outputs WHERE recipe_outputs.item_id = items.id), 0)
        SQL);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['usage_count']);
            $table->dropColumn('usage_count');
        });
    }
};
