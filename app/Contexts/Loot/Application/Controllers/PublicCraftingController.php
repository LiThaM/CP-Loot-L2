<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Domain\Models\Recipe;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCraftingController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:120',
            'chronicle' => 'nullable|string|max:20',
        ]);

        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $chronicle = trim((string) $request->query('chronicle', ''));

        $recipes = Recipe::query()
            ->when($chronicle, fn ($query) => $query->where('chronicle', $chronicle))
            ->where('name', 'like', '%'.$q.'%')
            ->with(['outputItem:id,name,image_url', 'materials'])
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (Recipe $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'chronicle' => $r->chronicle,
                'success_rate' => $r->success_rate,
                'mp_cost' => $r->mp_cost,
                'adena_fee' => $r->adena_fee,
                'materials_count' => $r->materials->count(),
                'output_item' => $r->outputItem ? [
                    'id' => $r->outputItem->id,
                    'name' => $r->outputItem->name,
                    'image_url' => $r->outputItem->image_url,
                ] : null,
            ]);

        return response()->json($recipes);
    }

    public function tree(Request $request, Recipe $recipe)
    {
        $request->validate([
            'depth' => 'nullable|integer|min:0|max:6',
        ]);

        $depth = (int) ($request->query('depth', 3));
        $recipe->load(['materials.item', 'outputs.item', 'outputItem', 'recipeItem']);

        $chronicle = $recipe->chronicle ?: 'IL';
        $craftableMap = $this->craftableRecipeIdByItemId($chronicle);
        $visited = [];

        $nodes = $recipe->materials->map(function ($mat) use ($depth, $chronicle, $craftableMap, &$visited) {
            return $this->buildNode(
                itemId: (int) $mat->item_id,
                name: $mat->item?->name,
                imageUrl: $mat->item?->image_url,
                need: (int) ($mat->quantity ?? 1),
                craftableMap: $craftableMap,
                depth: $depth,
                chronicle: $chronicle,
                visited: $visited,
            );
        })->values();

        if ($recipe->recipe_item_id) {
            $nodes->prepend([
                'item_id' => (int) $recipe->recipe_item_id,
                'name' => $recipe->recipeItem?->name ?? 'Recipe: '.$recipe->name,
                'image_url' => $recipe->recipeItem?->image_url,
                'need' => 1,
                'craft_recipe_id' => null,
                'children' => [],
                'is_recipe' => true,
            ]);
        }

        $outputs = [];
        if ($recipe->outputs->isNotEmpty()) {
            $outputs = $recipe->outputs->map(fn ($o) => [
                'item_id' => (int) $o->item_id,
                'name' => $o->item?->name,
                'image_url' => $o->item?->image_url,
                'quantity' => (int) ($o->quantity ?? 1),
                'chance' => $o->chance,
            ])->values();
        } elseif ($recipe->outputItem) {
            $outputs = collect([[
                'item_id' => (int) $recipe->outputItem->id,
                'name' => $recipe->outputItem->name,
                'image_url' => $recipe->outputItem->image_url,
                'quantity' => (int) ($recipe->output_quantity ?? 1),
                'chance' => null,
            ]]);
        }

        return response()->json([
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'chronicle' => $recipe->chronicle,
                'success_rate' => $recipe->success_rate,
                'mp_cost' => $recipe->mp_cost,
                'adena_fee' => $recipe->adena_fee,
            ],
            'outputs' => $outputs,
            'nodes' => $nodes,
        ]);
    }

    public function chronicles()
    {
        $chronicles = Recipe::query()
            ->select('chronicle')
            ->distinct()
            ->orderBy('chronicle')
            ->pluck('chronicle');

        return response()->json($chronicles);
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
            ->select(['recipe_outputs.item_id', 'recipe_outputs.recipe_id'])
            ->get()
            ->map(fn ($r) => ['item_id' => (int) $r->item_id, 'recipe_id' => (int) $r->recipe_id]);

        $map = [];
        foreach ($direct->concat($alt)->groupBy('item_id') as $itemId => $rows) {
            $map[(int) $itemId] = (int) $rows->sortBy('recipe_id')->first()['recipe_id'];
        }

        return $map;
    }

    private function buildNode(
        int $itemId,
        ?string $name,
        ?string $imageUrl,
        int $need,
        array $craftableMap,
        int $depth,
        string $chronicle,
        array &$visited,
    ): array {
        $craftRecipeId = $craftableMap[$itemId] ?? null;
        $children = [];

        if ($depth > 0 && $craftRecipeId && ! in_array($craftRecipeId, $visited, true)) {
            $visited[] = $craftRecipeId;
            $craftRecipe = Recipe::whereKey($craftRecipeId)
                ->where('chronicle', $chronicle)
                ->with(['materials.item'])
                ->first();

            if ($craftRecipe) {
                $children = $craftRecipe->materials->map(function ($mat) use ($need, $depth, $chronicle, $craftableMap, &$visited) {
                    return $this->buildNode(
                        itemId: (int) $mat->item_id,
                        name: $mat->item?->name,
                        imageUrl: $mat->item?->image_url,
                        need: (int) ($mat->quantity ?? 1) * max(1, $need),
                        craftableMap: $craftableMap,
                        depth: $depth - 1,
                        chronicle: $chronicle,
                        visited: $visited,
                    );
                })->values()->all();
            }
        }

        return [
            'item_id' => $itemId,
            'name' => $name,
            'image_url' => $imageUrl,
            'need' => $need,
            'craft_recipe_id' => $craftRecipeId,
            'children' => $children,
        ];
    }
}
