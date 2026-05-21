<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * This migration originally set Stone of Purity to x5 on LU4's
     * "Recipe: Varnish of Purity" based on a verbal report. The
     * authoritative source for LU4 recipes is masterwork.wiki — see
     * https://wikipedia1.mw2.wiki/lu4/item/2142 — and it confirms the
     * correct quantity is 1 (matches every other chronicle in our DB
     * AND matches the value the scraper originally seeded). The change
     * was wrong; this migration is now a no-op and restores the
     * original x1 on rerun.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::table('recipe_materials')
            ->where('recipe_id', function ($q) {
                $q->select('id')->from('recipes')
                    ->where('chronicle', 'LU4')
                    ->where('name', 'Recipe: Varnish of Purity')
                    ->limit(1);
            })
            ->where('item_id', function ($q) {
                $q->select('id')->from('items')
                    ->whereRaw('LOWER(name) = ?', ['stone of purity'])
                    ->where('chronicle', 'LU4')
                    ->limit(1);
            })
            ->update(['quantity' => 1]);
    }

    public function down(): void {}
};
