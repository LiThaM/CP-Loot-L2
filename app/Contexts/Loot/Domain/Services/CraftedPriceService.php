<?php

namespace App\Contexts\Loot\Domain\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Derives a "craft cost" market price for every craftable item in a chronicle
 * from the market prices of the materials its recipe consumes — e.g. a Coarse
 * Bone Powder that needs N Animal Bone is priced from the Animal Bone market
 * price. This is a second, computed price that sits alongside the manually-set
 * `items.market_price` (it never overwrites it).
 *
 * The cost of each material is its EFFECTIVE price, in this order:
 *   1. a manually-set market_price (the real cost to just buy it),
 *   2. otherwise the material's own craft cost (recursive — multi-level crafts
 *      like Artisan's Frame ← Steel Mold ← Iron Ore + ... resolve fully),
 *   3. otherwise its NPC sell price.
 * An item whose chain never bottoms out in a price stays unpriced (null).
 */
class CraftedPriceService
{
    /**
     * @return array<int,int> item_id → crafted price
     */
    public function mapForChronicle(string $chronicle): array
    {
        return Cache::remember(
            $this->cacheKey($chronicle),
            now()->addMinutes(10),
            fn () => $this->compute($chronicle)
        );
    }

    /**
     * Drop the cached map for a chronicle so the next read recomputes it.
     * Call this whenever a market price changes.
     */
    public function forget(string $chronicle): void
    {
        Cache::forget($this->cacheKey($chronicle));
    }

    private function cacheKey(string $chronicle): string
    {
        return "crafted_prices:{$chronicle}";
    }

    /**
     * @return array<int,int>
     */
    private function compute(string $chronicle): array
    {
        $price = [];
        $npc = [];
        foreach (DB::table('items')->where('chronicle', $chronicle)->get(['id', 'market_price', 'npc_sell_price']) as $it) {
            $price[$it->id] = $it->market_price !== null ? (int) $it->market_price : null;
            $npc[$it->id] = $it->npc_sell_price !== null ? (int) $it->npc_sell_price : null;
        }

        // First recipe per output item (lowest id wins when several produce it).
        $recByOut = [];
        foreach (DB::table('recipes')->where('chronicle', $chronicle)->orderBy('id')->get(['id', 'output_item_id', 'output_quantity', 'adena_fee']) as $r) {
            if ($r->output_item_id && ! isset($recByOut[$r->output_item_id])) {
                $recByOut[$r->output_item_id] = $r;
            }
        }

        $mats = [];
        foreach (DB::table('recipe_materials')->get(['recipe_id', 'item_id', 'quantity']) as $m) {
            $mats[$m->recipe_id][] = $m;
        }

        $craftCache = [];

        $effective = function ($id, array $stack) use (&$effective, &$craft, $price, $npc) {
            if (($price[$id] ?? null) !== null) {
                return $price[$id];
            }
            $crafted = $craft($id, $stack);
            if ($crafted !== null) {
                return $crafted;
            }

            return $npc[$id] ?? null;
        };

        $craft = function ($id, array $stack) use (&$craft, &$effective, $recByOut, $mats, &$craftCache) {
            if (array_key_exists($id, $craftCache)) {
                return $craftCache[$id];
            }
            // No recipe → not craftable. In-stack → recipe cycle, bail (uncached).
            if (! isset($recByOut[$id])) {
                return null;
            }
            if (isset($stack[$id])) {
                return null;
            }
            $stack[$id] = true;
            $recipe = $recByOut[$id];
            $sum = 0;
            $any = false;
            foreach (($mats[$recipe->id] ?? []) as $m) {
                $ep = $effective($m->item_id, $stack);
                if ($ep === null) {
                    return $craftCache[$id] = null;
                }
                $sum += $ep * (int) $m->quantity;
                $any = true;
            }
            if (! $any) {
                return $craftCache[$id] = null;
            }
            $sum += (int) ($recipe->adena_fee ?? 0);
            $qty = max(1, (int) ($recipe->output_quantity ?? 1));

            return $craftCache[$id] = (int) round($sum / $qty);
        };

        $out = [];
        foreach (array_keys($recByOut) as $outputId) {
            $value = $craft($outputId, []);
            if ($value !== null) {
                $out[$outputId] = $value;
            }
        }

        return $out;
    }
}
