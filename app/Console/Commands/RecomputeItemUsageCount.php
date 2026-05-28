<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recompute `items.usage_count` from the four FK sources (loot_entries,
 * wishlists, recipe_materials, recipe_outputs). Runs daily so the
 * search-autocomplete ranking stays fresh. The autocomplete tolerates
 * up-to-24h staleness, so we don't bother with realtime counter-cache.
 *
 * Single update per item is fine — there are ~22k items and the update
 * fits inside a few seconds even on a modest MySQL.
 */
class RecomputeItemUsageCount extends Command
{
    protected $signature = 'items:recompute-usage';

    protected $description = 'Recompute usage_count for every item from loot_entries + wishlists + recipe_materials + recipe_outputs.';

    public function handle(): int
    {
        $start = microtime(true);

        // Correlated subqueries — portable across MySQL and SQLite.
        // Each FK source contributes a COUNT; COALESCE turns missing
        // matches into 0. Single statement, no PHP row loop.
        $affected = DB::affectingStatement(<<<'SQL'
            UPDATE items
            SET usage_count =
                COALESCE((SELECT COUNT(*) FROM loot_entries WHERE loot_entries.item_id = items.id), 0)
              + COALESCE((SELECT COUNT(*) FROM wishlists WHERE wishlists.item_id = items.id), 0)
              + COALESCE((SELECT COUNT(*) FROM recipe_materials WHERE recipe_materials.item_id = items.id), 0)
              + COALESCE((SELECT COUNT(*) FROM recipe_outputs WHERE recipe_outputs.item_id = items.id), 0)
        SQL);

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("items:recompute-usage → {$affected} items updated in {$elapsed}s");

        return self::SUCCESS;
    }
}
