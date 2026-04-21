<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Domain\Models\CpRecipe;
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

        try {
            DB::transaction(function () use ($user, $cp, $recipe, $shouldProduce, $outputItemId) {
                $warehouseAmountsByItemId = $this->warehouseAmountsByItemId((int) $cp->id);
 
                // Check Materials
                foreach ($recipe->materials as $mat) {
                    $need = (int) ($mat->quantity ?? 1);
                    $have = (int) ($warehouseAmountsByItemId[$mat->item_id] ?? 0);
                    if ($have < $need) {
                        throw new \RuntimeException('NOT_ENOUGH_MATERIALS:'.$mat->item_id.':'.$need.':'.$have);
                    }
                }
 
                // Check Recipe Item
                if ($recipe->recipe_item_id) {
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
 
                // Consume materials
                foreach ($recipe->materials as $mat) {
                    LootEntry::create([
                        'loot_report_id' => $consumeReport->id,
                        'item_id' => $mat->item_id,
                        'amount' => (int) ($mat->quantity ?? 1),
                    ]);
                }
 
                // Consume recipe
                if ($recipe->recipe_item_id) {
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

        return response()->json([
            'ok' => true,
            'produced' => $shouldProduce,
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
        $visitedRecipeIds = [];

        $nodes = $recipe->materials->map(function ($mat) use ($depth, $chronicle, $amounts, $craftableRecipeIdByItemId, &$visitedRecipeIds) {
            return $this->treeNodeForMaterial(
                itemId: (int) $mat->item_id,
                name: $mat->item?->name,
                imageUrl: $mat->item?->image_url,
                need: (int) ($mat->quantity ?? 1),
                have: (int) ($amounts[$mat->item_id] ?? 0),
                craftableRecipeIdByItemId: $craftableRecipeIdByItemId,
                depth: $depth,
                chronicle: $chronicle,
                amounts: $amounts,
                visitedRecipeIds: $visitedRecipeIds,
            );
        })->values();
 
        // Inject Recipe Item as a mandatory node if exists
        if ($recipe->recipe_item_id) {
            $recipeItemNode = [
                'item_id' => (int) $recipe->recipe_item_id,
                'name' => $recipe->recipeItem?->name ?? 'Receta ' . $recipe->name,
                'image_url' => $recipe->recipeItem?->image_url,
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
        if (! $user->cp_id || $user->id !== $user->cp->leader_id) {
            abort(403, 'Solo el líder puede elegir las recetas.');
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

        if (! $user->cp_id || $user->id !== $user->cp->leader_id) {
            abort(403, 'Solo el líder puede elegir las recetas.');
        }

        if ($cpRecipe->cp_id !== $user->cp_id) {
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
        if (! $user->cp_id || $user->id !== $user->cp->leader_id) {
            abort(403, 'Solo el líder puede priorizar las recetas.');
        }

        if ($cpRecipe->cp_id !== $user->cp_id) {
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

    private function warehouseAmountsByItemId(int $cpId): array
    {
        $incoming = LootEntry::query()
            ->selectRaw('loot_entries.item_id, SUM(loot_entries.amount) as incoming_amount')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
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
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
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
        int $need,
        int $have,
        array $craftableRecipeIdByItemId,
        int $depth,
        string $chronicle,
        array $amounts,
        array &$visitedRecipeIds,
    ): array {
        $missing = max(0, $need - $have);
        $craftRecipeId = $craftableRecipeIdByItemId[$itemId] ?? null;
        $children = [];

        if ($depth > 0 && $missing > 0 && $craftRecipeId && ! in_array($craftRecipeId, $visitedRecipeIds, true)) {
            $visitedRecipeIds[] = $craftRecipeId;
            $craftRecipe = Recipe::whereKey($craftRecipeId)
                ->where('chronicle', $chronicle)
                ->with(['materials.item'])
                ->first();

            if ($craftRecipe) {
                $children = $craftRecipe->materials->map(function ($mat) use ($missing, $depth, $chronicle, $amounts, $craftableRecipeIdByItemId, &$visitedRecipeIds) {
                    $childNeed = (int) ($mat->quantity ?? 1) * max(1, $missing);
                    $childHave = (int) ($amounts[$mat->item_id] ?? 0);

                    return $this->treeNodeForMaterial(
                        itemId: (int) $mat->item_id,
                        name: $mat->item?->name,
                        imageUrl: $mat->item?->image_url,
                        need: $childNeed,
                        have: $childHave,
                        craftableRecipeIdByItemId: $craftableRecipeIdByItemId,
                        depth: $depth - 1,
                        chronicle: $chronicle,
                        amounts: $amounts,
                        visitedRecipeIds: $visitedRecipeIds,
                    );
                })->values()->all();
            }
        }

        return [
            'item_id' => $itemId,
            'name' => $name,
            'image_url' => $imageUrl,
            'need' => $need,
            'have' => $have,
            'missing' => $missing,
            'craft_recipe_id' => $craftRecipeId,
            'children' => $children,
        ];
    }
}
