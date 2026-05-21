<?php

namespace App\Contexts\Loot\Application\Services;

use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\Recipe;
use Illuminate\Support\Facades\DB;

/**
 * Plans a multi-recipe craft for a CP leader: aggregates the materials of
 * every requested recipe, subtracts the current Warehouse CP stock, and
 * recursively sub-crafts whatever the warehouse cannot cover, stopping at
 * leaves (items without a recipe in this chronicle).
 *
 * Read-only — does NOT touch the warehouse or commit any loot_report. The
 * caller (controller) is responsible for authorization.
 */
class CraftBulkPlannerService
{
    public const MAX_DEPTH = 12;

    /**
     * @param array<int,array{recipe_id:int,qty:int}> $orders
     * @return array<string,mixed>
     */
    public function plan(int $cpId, string $chronicle, array $orders): array
    {
        if (empty($orders)) {
            return $this->emptyResult();
        }

        $recipes = Recipe::query()
            ->whereIn('id', array_column($orders, 'recipe_id'))
            ->where('chronicle', $chronicle)
            ->with(['materials.item', 'outputItem'])
            ->get()
            ->keyBy('id');

        // Validate every requested recipe is in this chronicle. Any miss
        // means the controller's validation slipped — abort cleanly.
        $warnings = [];
        $orderedRequests = [];
        foreach ($orders as $o) {
            $id = (int) $o['recipe_id'];
            $qty = max(1, (int) $o['qty']);
            $r = $recipes->get($id);
            if (!$r) {
                $warnings[] = "Recipe #{$id} is not in chronicle {$chronicle} — skipped.";
                continue;
            }
            $orderedRequests[] = [
                'recipe' => $this->recipePayload($r),
                'qty' => $qty,
            ];
        }
        if (empty($orderedRequests)) {
            return array_merge($this->emptyResult(), ['warnings' => $warnings]);
        }

        // Aggregate top-level demand: how many of each leaf material the
        // requested outputs would need if we ignored stock and never
        // sub-crafted. `$sources` tracks WHY each material is being
        // demanded (which output / which sub-craft) so the UI can show
        // an expandable "Para X: 8, para Y: 4" breakdown per row.
        $demand = []; // itemId => int
        $sources = []; // itemId => [label => qty]
        foreach ($orderedRequests as $req) {
            $recipe = $recipes->get($req['recipe']['id']);
            $label = $recipe->outputItem?->name ?: $recipe->name;
            $this->addRecipeDemand($demand, $sources, $recipe, $req['qty'], $label);
        }

        // Stock is fetched lazily inside the loop: any item that first
        // shows up in `$demand` (top-level or via a sub-craft) gets its
        // warehouse stock pulled before being processed, so an
        // intermediate craftable like Cord still consumes its stock even
        // when it only appears at depth 2+.
        $stock = [];
        $craftableMap = Item::craftableRecipeIdByItemId($chronicle);

        // Sub-craft loop: walk the demand, consume stock, queue sub-crafts
        // for the missing+craftable, stop on leaves.
        $consumed = [];       // itemId => qty taken from stock
        $subCrafts = [];      // ordered list of crafts the planner decided
        $leaves = [];         // itemId => total still-missing (incl. partial sub-craft consumption)
        $iterations = 0;
        $ancestors = [];      // per-item recipe ids to avoid infinite recursion
        // Each entry of $demand may grow as sub-crafts add their own
        // material needs. Process iteratively rather than recursively.
        while (!empty($demand) && $iterations++ < 50) {
            $newItemIds = array_values(array_diff(array_keys($demand), array_keys($stock)));
            if (!empty($newItemIds)) {
                $stock = $stock + $this->stockForCp($cpId, $newItemIds);
            }
            $next = [];
            foreach ($demand as $itemId => $needed) {
                if ($needed <= 0) {
                    continue;
                }
                $available = max(0, ($stock[$itemId] ?? 0) - ($consumed[$itemId] ?? 0));
                $taken = min($available, $needed);
                $consumed[$itemId] = ($consumed[$itemId] ?? 0) + $taken;
                $missing = $needed - $taken;
                if ($missing <= 0) {
                    continue;
                }
                $craftRecipeId = $craftableMap[$itemId] ?? null;
                if (!$craftRecipeId) {
                    // Leaf — record what's still missing.
                    $leaves[$itemId] = ($leaves[$itemId] ?? 0) + $missing;
                    continue;
                }
                if (!empty($ancestors[$itemId]) && in_array($craftRecipeId, $ancestors[$itemId], true)) {
                    $warnings[] = "Cycle detected on item #{$itemId} via recipe #{$craftRecipeId} — keeping it as a leaf.";
                    $leaves[$itemId] = ($leaves[$itemId] ?? 0) + $missing;
                    continue;
                }

                $subRecipe = Recipe::with(['materials.item', 'outputItem'])->find($craftRecipeId);
                if (!$subRecipe) {
                    $leaves[$itemId] = ($leaves[$itemId] ?? 0) + $missing;
                    continue;
                }
                $outputQty = max(1, (int) ($subRecipe->output_quantity ?? 1));
                $crafts = (int) ceil($missing / $outputQty);
                $produces = $crafts * $outputQty;
                $subCrafts[] = [
                    'recipe' => $this->recipePayload($subRecipe),
                    'crafts' => $crafts,
                    'produces' => $produces,
                    'covers_item_id' => $itemId,
                    'covers_missing' => $missing,
                ];
                // The over-produced excess (produces - missing) stays in
                // `consumed` so future demand of the same item sees it as
                // already covered.
                $consumed[$itemId] = ($consumed[$itemId] ?? 0) - ($produces - $missing);
                // Propagate the sub-recipe's own material demand into the
                // queue, multiplied by `crafts`. Tag the source so the UI
                // can show "Para Crafted Leather: 8 Coal" later.
                $subLabel = $subRecipe->outputItem?->name ?: $subRecipe->name;
                foreach ($subRecipe->materials as $mat) {
                    $matId = (int) $mat->item_id;
                    $addQty = ((int) ($mat->quantity ?? 1)) * $crafts;
                    $next[$matId] = ($next[$matId] ?? 0) + $addQty;
                    $sources[$matId][$subLabel] = ($sources[$matId][$subLabel] ?? 0) + $addQty;
                    $ancestors[$matId] = array_merge($ancestors[$itemId] ?? [], [$craftRecipeId]);
                }
            }
            $demand = $next;
        }

        if ($iterations >= 50) {
            $warnings[] = 'Planner reached its iteration safety limit. Result may be incomplete.';
        }

        $allItemIds = array_unique(array_merge(array_keys($leaves), array_keys($consumed)));

        $itemMeta = Item::whereIn('id', $allItemIds)
            ->get(['id', 'name', 'image_url'])
            ->keyBy('id');

        $totals = [];
        foreach ($leaves as $itemId => $missing) {
            $meta = $itemMeta->get($itemId);
            $usagesRaw = $sources[$itemId] ?? [];
            arsort($usagesRaw);
            $usages = [];
            foreach ($usagesRaw as $label => $qty) {
                $usages[] = ['for' => (string) $label, 'qty' => (int) $qty];
            }
            $need = $missing + (int) ($consumed[$itemId] ?? 0);
            $have = (int) ($stock[$itemId] ?? 0);
            $totals[] = [
                'item_id' => $itemId,
                'name' => $meta?->name,
                'image_url' => $meta?->image_url,
                'need' => $need,
                'have' => $have,
                // Always equals `max(0, need - have)` so the row's
                // arithmetic matches what the user reads on screen, even
                // in edge cases where sub-crafts shuffle `consumed`.
                'missing' => max(0, $need - $have),
                'usages' => $usages,
            ];
        }
        // Stable order: missing-first, then by name.
        usort($totals, function ($a, $b) {
            if (($b['missing'] <=> $a['missing']) !== 0) {
                return $b['missing'] <=> $a['missing'];
            }
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'requests' => $orderedRequests,
            'totals' => $totals,
            'sub_crafts' => array_map(function ($sc) use ($itemMeta) {
                $sc['covers_item_name'] = $itemMeta->get($sc['covers_item_id'])?->name;
                return $sc;
            }, $subCrafts),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function emptyResult(): array
    {
        return [
            'requests' => [],
            'totals' => [],
            'sub_crafts' => [],
            'warnings' => [],
        ];
    }

    private function recipePayload(Recipe $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'output_qty' => (int) ($r->output_quantity ?? 1),
            'output_item' => $r->outputItem ? [
                'id' => $r->outputItem->id,
                'name' => $r->outputItem->name,
                'image_url' => $r->outputItem->image_url,
            ] : null,
            'materials' => $r->relationLoaded('materials')
                ? $r->materials->map(fn ($m) => [
                    'item_id' => (int) $m->item_id,
                    'name' => $m->item?->name,
                    'image_url' => $m->item?->image_url,
                    'qty_per_craft' => (int) ($m->quantity ?? 1),
                ])->values()->all()
                : [],
        ];
    }

    /**
     * Add a recipe's direct material demand (output_qty * crafts) to the
     * running demand map. `crafts = ceil(qtyRequested / output_qty)`.
     * Also stamps each added quantity under `$sources[itemId][$label]` so
     * the planner can render "Para X: N" later in the totals breakdown.
     */
    private function addRecipeDemand(array &$demand, array &$sources, Recipe $recipe, int $qtyRequested, string $label): void
    {
        $outputQty = max(1, (int) ($recipe->output_quantity ?? 1));
        $crafts = (int) ceil($qtyRequested / $outputQty);
        foreach ($recipe->materials as $mat) {
            $itemId = (int) $mat->item_id;
            $addQty = ((int) ($mat->quantity ?? 1)) * $crafts;
            $demand[$itemId] = ($demand[$itemId] ?? 0) + $addQty;
            $sources[$itemId][$label] = ($sources[$itemId][$label] ?? 0) + $addQty;
        }
    }

    /**
     * Current warehouse stock for a CP, per item. Same incoming/outgoing
     * pattern that `PartyController::index` uses inline — bundled into one
     * grouped query so the planner doesn't N+1.
     *
     * @param  array<int,int> $itemIds
     * @return array<int,int> itemId => qty available
     */
    private function stockForCp(int $cpId, array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $incoming = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
            ->whereIn('loot_entries.item_id', $itemIds)
            ->groupBy('loot_entries.item_id')
            ->select('loot_entries.item_id', DB::raw('SUM(loot_entries.amount) as total'))
            ->pluck('total', 'loot_entries.item_id')
            ->toArray();

        $outgoing = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
            ->whereIn('loot_entries.item_id', $itemIds)
            ->groupBy('loot_entries.item_id')
            ->select('loot_entries.item_id', DB::raw('SUM(loot_entries.amount) as total'))
            ->pluck('total', 'loot_entries.item_id')
            ->toArray();

        $stock = [];
        foreach ($itemIds as $id) {
            $stock[(int) $id] = max(0, (int) ($incoming[$id] ?? 0) - (int) ($outgoing[$id] ?? 0));
        }
        return $stock;
    }
}
