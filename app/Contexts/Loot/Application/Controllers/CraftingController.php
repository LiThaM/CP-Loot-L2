<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Domain\Models\CpRecipe;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CraftingController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:120',
        ]);

        $user = $request->user();
        if (! $user->cp_id) {
            abort(403);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $chronicle = $user->cp->chronicle ?: 'IL';

        $recipes = Recipe::query()
            ->where('chronicle', $chronicle)
            ->where('name', 'like', '%'.$q.'%')
            ->with(['outputItem:id,name,image_url'])
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function (Recipe $recipe) {
                return [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                    'success_rate' => $recipe->success_rate,
                    'output_item' => $recipe->outputItem ? [
                        'id' => $recipe->outputItem->id,
                        'name' => $recipe->outputItem->name,
                        'image_url' => $recipe->outputItem->image_url,
                    ] : null,
                ];
            });

        return response()->json($recipes);
    }

    public function craft(Request $request, Recipe $recipe)
    {
        $request->validate([
            'lucky' => 'nullable|boolean',
            'output_item_id' => 'nullable|integer|exists:items,id',
        ]);

        $user = $request->user();
        $roleName = $user->role?->name;
        if (! in_array($roleName, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403, 'No tienes permiso para craftear usando el warehouse.');
        }
        if (! $user->cp_id) {
            abort(403);
        }

        $cp = $user->cp;
        $chronicle = $cp->chronicle ?: 'IL';
        if ($recipe->chronicle !== $chronicle) {
            abort(422, 'La receta no pertenece al mismo cronicón del CP.');
        }

        $recipe->load(['materials', 'outputs', 'outputItem', 'recipeItem']);

        $successRate = (float) ($recipe->success_rate ?? 0);
        $lucky = $request->boolean('lucky', $successRate >= 100);
        $shouldProduce = $successRate >= 100 || $lucky;

        $outputItemId = $request->input('output_item_id');
        if ($outputItemId) {
            $isAllowedOutput = (int) $recipe->output_item_id === (int) $outputItemId
                || $recipe->outputs->contains(fn ($o) => (int) $o->item_id === (int) $outputItemId);
            if (! $isAllowedOutput) {
                return response()->json(['message' => 'El resultado seleccionado no pertenece a esta receta.'], 422);
            }
        } else {
            if ($recipe->outputs->count() > 0) {
                $outputItemId = (int) $recipe->outputs->first()->item_id;
            } else {
                $outputItemId = (int) ($recipe->output_item_id ?? 0);
            }
        }

        $toConsume = []; // item_id => amount (filled inside the transaction)
        $autoCrafted = []; // item_id => amount of intermediates auto-crafted
        try {
            DB::transaction(function () use ($user, $cp, $recipe, $shouldProduce, $outputItemId, &$toConsume, &$autoCrafted) {
                $warehouseAmountsByItemId = $this->warehouseAmountsByItemId((int) $cp->id);
                $chronicle = $cp->chronicle ?: 'IL';
                $craftableMap = $this->craftableRecipeIdByItemId($chronicle);

                $available = [];
                foreach ($warehouseAmountsByItemId as $id => $amt) {
                    $available[(int) $id] = (int) $amt;
                }

                $resolveMaterial = function (int $itemId, int $need) use (&$resolveMaterial, &$toConsume, &$autoCrafted, &$available, $craftableMap): void {
                    $have = $available[$itemId] ?? 0;

                    if ($have >= $need) {
                        $toConsume[$itemId] = ($toConsume[$itemId] ?? 0) + $need;
                        $available[$itemId] -= $need;
                        return;
                    }

                    $shortfall = $need - $have;
                    if ($have > 0) {
                        $toConsume[$itemId] = ($toConsume[$itemId] ?? 0) + $have;
                        $available[$itemId] = 0;
                    }

                    $subRecipeId = $craftableMap[$itemId] ?? null;
                    if (! $subRecipeId) {
                        throw new \RuntimeException('NOT_ENOUGH_MATERIALS:'.$itemId.':'.$need.':'.max(0, $have));
                    }

                    $subRecipe = Recipe::whereKey($subRecipeId)->with('materials')->first();
                    if (! $subRecipe || $subRecipe->materials->isEmpty()) {
                        throw new \RuntimeException('NOT_ENOUGH_MATERIALS:'.$itemId.':'.$need.':'.max(0, $have));
                    }

                    $outputQty = max(1, (int) ($subRecipe->output_quantity ?? 1));
                    $craftsNeeded = (int) ceil($shortfall / $outputQty);
                    $autoCrafted[$itemId] = ($autoCrafted[$itemId] ?? 0) + ($craftsNeeded * $outputQty);

                    foreach ($subRecipe->materials as $subMat) {
                        $subNeed = (int) ($subMat->quantity ?? 1) * $craftsNeeded;
                        $resolveMaterial((int) $subMat->item_id, $subNeed);
                    }
                };

                foreach ($recipe->materials as $mat) {
                    $resolveMaterial((int) $mat->item_id, (int) ($mat->quantity ?? 1));
                }

                // Check Recipe Item — only when the FINAL output is a non-Material
                // (Weapon, Armor, Jewelry, Accessory). For Materials the recipe
                // scroll is not required (L2 sub-craft semantics).
                $requiresScroll = $recipe->recipe_item_id && $this->requiresRecipeScroll($recipe, (int) $outputItemId);
                if ($requiresScroll) {
                    $haveRecipe = (int) ($warehouseAmountsByItemId[$recipe->recipe_item_id] ?? 0);
                    if ($haveRecipe < 1) {
                        throw new \RuntimeException('NOT_ENOUGH_MATERIALS:'.$recipe->recipe_item_id.':1:'.$haveRecipe);
                    }
                }

                $consumeReport = LootReport::create([
                    'cp_id' => $cp->id,
                    'requested_by_id' => $user->id,
                    'event_type' => 'WAREHOUSE_CRAFT_CONSUME',
                    'status' => 'confirmed',
                    'image_proof' => null,
                    'recipient_ids' => null,
                ]);

                // Consume all materials (direct + auto-crafted sub-materials)
                foreach ($toConsume as $itemId => $amount) {
                    if ($amount <= 0) {
                        continue;
                    }
                    LootEntry::create([
                        'loot_report_id' => $consumeReport->id,
                        'item_id' => $itemId,
                        'amount' => $amount,
                    ]);
                }

                // Consume recipe scroll (only when required — Material outputs skip)
                if ($requiresScroll) {
                    LootEntry::create([
                        'loot_report_id' => $consumeReport->id,
                        'item_id' => $recipe->recipe_item_id,
                        'amount' => 1,
                    ]);
                }

                if (! $shouldProduce || (int) $outputItemId <= 0) {
                    return;
                }

                $produceReport = LootReport::create([
                    'cp_id' => $cp->id,
                    'requested_by_id' => $user->id,
                    'event_type' => 'WAREHOUSE_CRAFT_PRODUCE',
                    'status' => 'confirmed',
                    'image_proof' => null,
                    'recipient_ids' => null,
                ]);

                LootEntry::create([
                    'loot_report_id' => $produceReport->id,
                    'item_id' => $outputItemId,
                    'amount' => max(1, (int) ($recipe->output_quantity ?? 1)),
                ]);
            });
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'NOT_ENOUGH_MATERIALS:')) {
                [$tag, $itemId, $need, $have] = array_pad(explode(':', $e->getMessage()), 4, null);

                return response()->json([
                    'message' => 'Materiales insuficientes para craftear.',
                    'missing' => [
                        'item_id' => (int) ($itemId ?? 0),
                        'need' => (int) ($need ?? 0),
                        'have' => (int) ($have ?? 0),
                    ],
                ], 422);
            }

            throw $e;
        }

        $hydrate = function (array $byId) {
            if (empty($byId)) return [];
            $items = Item::whereIn('id', array_keys($byId))->get(['id', 'name', 'image_url'])->keyBy('id');
            $out = [];
            foreach ($byId as $id => $qty) {
                $row = $items[$id] ?? null;
                $out[] = [
                    'item_id' => (int) $id,
                    'name' => $row?->name,
                    'image_url' => $row?->image_url,
                    'amount' => (int) $qty,
                ];
            }
            return $out;
        };

        $produced = $shouldProduce && $outputItemId
            ? $hydrate([$outputItemId => max(1, (int) ($recipe->output_quantity ?? 1))])
            : [];

        return response()->json([
            'ok' => true,
            'produced' => $shouldProduce,
            'consumed' => $hydrate($toConsume),
            'auto_crafted' => $hydrate($autoCrafted),
            'produced_items' => $produced,
        ]);
    }

    public function tree(Request $request, Recipe $recipe)
    {
        $request->validate([
            'depth' => 'nullable|integer|min:0|max:6',
        ]);

        $user = $request->user();
        if (! $user->cp_id) {
            abort(403);
        }

        $chronicle = $user->cp->chronicle ?: 'IL';
        if ($recipe->chronicle !== $chronicle) {
            abort(422, 'La receta no pertenece al mismo cronicón del CP.');
        }

        $depth = (int) ($request->query('depth', 3));
        $recipe->load(['materials.item', 'outputs.item', 'outputItem']);

        $amounts = $this->warehouseAmountsByItemId((int) $user->cp_id);
        $craftableRecipeIdByItemId = $this->craftableRecipeIdByItemId($chronicle);
        $nodes = $recipe->materials->map(function ($mat) use ($depth, $chronicle, $amounts, $craftableRecipeIdByItemId) {
            return $this->treeNodeForMaterial(
                itemId: (int) $mat->item_id,
                name: $mat->item?->name,
                imageUrl: $mat->item?->image_url,
                marketPrice: $mat->item?->market_price !== null ? (int) $mat->item->market_price : null,
                npcSellPrice: $mat->item?->npc_sell_price !== null ? (int) $mat->item->npc_sell_price : null,
                need: (int) ($mat->quantity ?? 1),
                have: (int) ($amounts[$mat->item_id] ?? 0),
                craftableRecipeIdByItemId: $craftableRecipeIdByItemId,
                depth: $depth,
                chronicle: $chronicle,
                amounts: $amounts,
                ancestors: [],
            );
        })->values();

        // Inject Recipe Item as a mandatory node if exists
        if ($recipe->recipe_item_id) {
            $recipeItemNode = [
                'item_id' => (int) $recipe->recipe_item_id,
                'name' => $recipe->recipeItem?->name ?? 'Receta ' . $recipe->name,
                'image_url' => $recipe->recipeItem?->image_url,
                'market_price' => $recipe->recipeItem?->market_price !== null ? (int) $recipe->recipeItem->market_price : null,
                'npc_sell_price' => $recipe->recipeItem?->npc_sell_price !== null ? (int) $recipe->recipeItem->npc_sell_price : null,
                'need' => 1,
                'have' => (int) ($amounts[$recipe->recipe_item_id] ?? 0),
                'missing' => max(0, 1 - (int) ($amounts[$recipe->recipe_item_id] ?? 0)),
                'craft_recipe_id' => null, // Recipes are not crafted in this system generally
                'children' => [],
                'is_recipe' => true,
            ];
            $nodes->prepend($recipeItemNode);
        }

        return response()->json([
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'success_rate' => $recipe->success_rate,
                'adena_fee' => (int) ($recipe->adena_fee ?? 0),
            ],
            'nodes' => $nodes,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'recipe_id' => 'required|exists:recipes,id',
        ]);

        $user = $request->user();
        if (! $this->canManageCpRecipes($user)) {
            abort(403, 'No tienes permiso para gestionar recetas del CP.');
        }

        $recipe = Recipe::findOrFail($request->recipe_id);
        if ($recipe->chronicle !== ($user->cp->chronicle ?: 'IL')) {
            abort(422, 'La receta no pertenece al mismo cronicón del CP.');
        }

        $maxPriority = (int) CpRecipe::where('cp_id', $user->cp_id)->max('priority');

        CpRecipe::updateOrCreate(
            ['cp_id' => $user->cp_id, 'recipe_id' => $recipe->id],
            ['priority' => $maxPriority + 1, 'created_by' => $user->id]
        );

        return back()->with('success', 'Receta añadida a la prioridad del CP.');
    }

    public function destroy(Request $request, CpRecipe $cpRecipe)
    {
        $user = $request->user();

        if (! $this->canManageCpRecipes($user)) {
            abort(403, 'No tienes permiso para gestionar recetas del CP.');
        }

        if ($cpRecipe->cp_id !== $user->cp_id && $user->role?->name !== 'admin') {
            abort(403);
        }

        $cpRecipe->delete();

        return back()->with('success', 'Receta eliminada de la prioridad del CP.');
    }

    public function move(Request $request, CpRecipe $cpRecipe)
    {
        $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        $user = $request->user();
        if (! $this->canManageCpRecipes($user)) {
            abort(403, 'No tienes permiso para gestionar recetas del CP.');
        }

        if ($cpRecipe->cp_id !== $user->cp_id && $user->role?->name !== 'admin') {
            abort(403);
        }

        $direction = (string) $request->input('direction');

        $neighbor = CpRecipe::query()
            ->where('cp_id', $cpRecipe->cp_id)
            ->whereKeyNot($cpRecipe->id)
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('priority', '<', $cpRecipe->priority)->orderByDesc('priority'),
                fn ($q) => $q->where('priority', '>', $cpRecipe->priority)->orderBy('priority'),
            )
            ->first();

        if (! $neighbor) {
            return back();
        }

        DB::transaction(function () use ($cpRecipe, $neighbor) {
            $tmp = $cpRecipe->priority;
            $cpRecipe->priority = $neighbor->priority;
            $neighbor->priority = $tmp;
            $cpRecipe->save();
            $neighbor->save();
        });

        return back()->with('success', 'Prioridad actualizada.');
    }

    private function canManageCpRecipes(?\App\Contexts\Identity\Domain\Models\User $user): bool
    {
        if (! $user) return false;
        $role = $user->role?->name;
        if ($role === 'admin') return true;
        if (! $user->cp_id) return false;
        return in_array($role, ['cp_leader', 'accountant'], true);
    }

    // L2 distinguishes craftables that consume a scroll-recipe (the
    // top-tier final pieces: Weapons, Armors, Jewelry, Accessories) from
    // intermediate materials whose recipe is a blueprint that the player
    // never "owns" as a consumable (Cord, Leather, Steel). The DB doesn't
    // model this distinction so we infer from the output's category.
    public static function requiresRecipeScroll(Recipe $recipe, int $outputItemId): bool
    {
        if (! $recipe->recipe_item_id) return false;
        $category = Item::where('id', $outputItemId)->value('category');
        return ! in_array($category, ['Material', 'EtcItem', 'Recipe'], true);
    }

    // Dry-run of the same recursive resolver used by craft(). Returns
    // `null` when the recipe cannot be covered with current stock, or an
    // array { auto_crafted: [{item_id, amount}], consumed: [{item_id, amount}] }
    // describing the plan. Used by the manual-crafting tab to enable the
    // Craft button when auto-allocation can succeed.
    public static function simulate(Recipe $recipe, iterable $warehouseAmountsByItemId, iterable $craftableMap): ?array
    {
        $craftableMap = $craftableMap instanceof \Traversable ? iterator_to_array($craftableMap) : (array) $craftableMap;
        $toConsume = [];
        $autoCrafted = [];
        $available = [];
        foreach ($warehouseAmountsByItemId as $id => $amt) {
            $available[(int) $id] = (int) $amt;
        }

        $resolve = function (int $itemId, int $need) use (&$resolve, &$toConsume, &$autoCrafted, &$available, $craftableMap): bool {
            $have = $available[$itemId] ?? 0;
            if ($have >= $need) {
                $toConsume[$itemId] = ($toConsume[$itemId] ?? 0) + $need;
                $available[$itemId] -= $need;
                return true;
            }
            $shortfall = $need - $have;
            if ($have > 0) {
                $toConsume[$itemId] = ($toConsume[$itemId] ?? 0) + $have;
                $available[$itemId] = 0;
            }
            $subRecipeId = $craftableMap[$itemId] ?? null;
            if (! $subRecipeId) return false;
            $subRecipe = Recipe::whereKey($subRecipeId)->with('materials')->first();
            if (! $subRecipe || $subRecipe->materials->isEmpty()) return false;
            $outputQty = max(1, (int) ($subRecipe->output_quantity ?? 1));
            $craftsNeeded = (int) ceil($shortfall / $outputQty);
            $autoCrafted[$itemId] = ($autoCrafted[$itemId] ?? 0) + ($craftsNeeded * $outputQty);
            foreach ($subRecipe->materials as $subMat) {
                $subNeed = (int) ($subMat->quantity ?? 1) * $craftsNeeded;
                if (! $resolve((int) $subMat->item_id, $subNeed)) return false;
            }
            return true;
        };

        foreach ($recipe->materials as $mat) {
            if (! $resolve((int) $mat->item_id, (int) ($mat->quantity ?? 1))) return null;
        }

        return ['auto_crafted' => $autoCrafted, 'consumed' => $toConsume];
    }

    private function warehouseAmountsByItemId(int $cpId): array
    {
        $incoming = LootEntry::query()
            ->selectRaw('loot_entries.item_id, SUM(loot_entries.amount) as incoming_amount')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('loot_entries.item_id')
            ->pluck('incoming_amount', 'loot_entries.item_id')
            ->all();

        $outgoing = LootEntry::query()
            ->selectRaw('loot_entries.item_id, SUM(loot_entries.amount) as outgoing_amount')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('loot_entries.item_id')
            ->pluck('outgoing_amount', 'loot_entries.item_id')
            ->all();

        $ids = array_unique(array_merge(array_keys($incoming), array_keys($outgoing)));
        $res = [];
        foreach ($ids as $id) {
            $in = (int) ($incoming[$id] ?? 0);
            $out = (int) ($outgoing[$id] ?? 0);
            $res[$id] = max(0, $in - $out);
        }

        return $res;
    }

    private function craftableRecipeIdByItemId(string $chronicle): array
    {
        $direct = Recipe::query()
            ->select(['id', 'output_item_id'])
            ->where('chronicle', $chronicle)
            ->whereNotNull('output_item_id')
            ->get()
            ->map(fn ($r) => ['item_id' => (int) $r->output_item_id, 'recipe_id' => (int) $r->id]);

        $alt = DB::table('recipe_outputs')
            ->join('recipes', 'recipes.id', '=', 'recipe_outputs.recipe_id')
            ->where('recipes.chronicle', $chronicle)
            ->select(['recipe_outputs.item_id as item_id', 'recipe_outputs.recipe_id as recipe_id'])
            ->get()
            ->map(fn ($r) => ['item_id' => (int) $r->item_id, 'recipe_id' => (int) $r->recipe_id]);

        $all = $direct->concat($alt)->groupBy('item_id');
        $map = [];
        foreach ($all as $itemId => $rows) {
            $best = $rows->sortBy('recipe_id')->first();
            if ($best) {
                $map[(int) $itemId] = (int) $best['recipe_id'];
            }
        }

        return $map;
    }

    private function treeNodeForMaterial(
        int $itemId,
        ?string $name,
        ?string $imageUrl,
        ?int $marketPrice,
        ?int $npcSellPrice,
        int $need,
        int $have,
        array $craftableRecipeIdByItemId,
        int $depth,
        string $chronicle,
        array $amounts,
        array $ancestors = [],
    ): array {
        $missing = max(0, $need - $have);
        $craftRecipeId = $craftableRecipeIdByItemId[$itemId] ?? null;
        $children = [];

        // Expand if depth allows and not a circular dependency in current branch
        if ($depth > 0 && $missing > 0 && $craftRecipeId && ! in_array($craftRecipeId, $ancestors, true)) {
            $craftRecipe = Recipe::whereKey($craftRecipeId)
                ->where('chronicle', $chronicle)
                ->with(['materials.item'])
                ->first();

            if ($craftRecipe) {
                $branchAncestors = array_merge($ancestors, [$craftRecipeId]);
                $children = $craftRecipe->materials->map(function ($mat) use ($missing, $depth, $chronicle, $amounts, $craftableRecipeIdByItemId, $branchAncestors) {
                    $childNeed = (int) ($mat->quantity ?? 1) * max(1, $missing);
                    $childHave = (int) ($amounts[$mat->item_id] ?? 0);

                    return $this->treeNodeForMaterial(
                        itemId: (int) $mat->item_id,
                        name: $mat->item?->name,
                        imageUrl: $mat->item?->image_url,
                        marketPrice: $mat->item?->market_price !== null ? (int) $mat->item->market_price : null,
                        npcSellPrice: $mat->item?->npc_sell_price !== null ? (int) $mat->item->npc_sell_price : null,
                        need: $childNeed,
                        have: $childHave,
                        craftableRecipeIdByItemId: $craftableRecipeIdByItemId,
                        depth: $depth - 1,
                        chronicle: $chronicle,
                        amounts: $amounts,
                        ancestors: $branchAncestors,
                    );
                })->values()->all();
            }
        }

        return [
            'item_id' => $itemId,
            'name' => $name,
            'image_url' => $imageUrl,
            'market_price' => $marketPrice,
            'npc_sell_price' => $npcSellPrice,
            'need' => $need,
            'have' => $have,
            'missing' => $missing,
            'craft_recipe_id' => $craftRecipeId,
            'children' => $children,
        ];
    }
}
