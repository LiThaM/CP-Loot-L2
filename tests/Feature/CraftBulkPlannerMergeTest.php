<?php

namespace Tests\Feature;

use App\Contexts\Loot\Application\Services\CraftBulkPlannerService;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Loot\Domain\Models\RecipeMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: the planner used to push one row into `sub_crafts` per
 * iteration that demanded an intermediate, so the same crafted item
 * (e.g. Steel) showed up twice when two parent branches both needed
 * to auto-craft it. After the fix, sub-crafts collapse by item_id.
 */
class CraftBulkPlannerMergeTest extends TestCase
{
    use RefreshDatabase;

    private CraftBulkPlannerService $planner;
    private string $chronicle = 'IL';
    private int $cpId = 999; // any int — the planner only reads stock with it.

    private Item $coal;
    private Item $steel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new CraftBulkPlannerService();
        Item::create(['name' => 'Adena', 'category' => 'EtcItem', 'chronicle' => $this->chronicle]);
        $this->coal  = Item::create(['name' => 'Coal',  'category' => 'Material', 'chronicle' => $this->chronicle]);
        $this->steel = Item::create(['name' => 'Steel', 'category' => 'Material', 'chronicle' => $this->chronicle]);
    }

    private function makeRecipe(string $name, int $externalId, ?Item $output, array $materials): Recipe
    {
        $recipe = Recipe::create([
            'external_id' => $externalId,
            'name' => $name,
            'chronicle' => $this->chronicle,
            'success_rate' => 100,
            'output_item_id' => $output?->id,
            'output_quantity' => 1,
        ]);
        foreach ($materials as [$itemId, $qty]) {
            RecipeMaterial::create([
                'recipe_id' => $recipe->id,
                'item_id' => $itemId,
                'quantity' => $qty,
            ]);
        }
        return $recipe;
    }

    public function test_two_orders_needing_the_same_subcraft_collapse(): void
    {
        // Both Armor1 and Armor2 need Steel directly. Two top-level
        // orders → planner sees Steel demand from BOTH branches.
        $armor1Out = Item::create(['name' => 'Armor1', 'category' => 'Armor', 'chronicle' => $this->chronicle]);
        $armor2Out = Item::create(['name' => 'Armor2', 'category' => 'Armor', 'chronicle' => $this->chronicle]);

        $steelRecipe = $this->makeRecipe('Steel Recipe', 5001, $this->steel, [[$this->coal->id, 5]]);
        $armor1      = $this->makeRecipe('Armor 1',     5002, $armor1Out,    [[$this->steel->id, 10]]);
        $armor2      = $this->makeRecipe('Armor 2',     5003, $armor2Out,    [[$this->steel->id, 7]]);

        $result = $this->planner->plan($this->cpId, $this->chronicle, [
            ['recipe_id' => $armor1->id, 'qty' => 1],
            ['recipe_id' => $armor2->id, 'qty' => 1],
        ]);

        $steelRows = collect($result['sub_crafts'])->filter(fn ($r) => $r['covers_item_id'] === $this->steel->id)->values();
        $this->assertCount(1, $steelRows, 'Steel must appear ONCE in sub_crafts even when demanded by two parents.');
        $this->assertSame(17, $steelRows[0]['covers_missing']); // 10 + 7
        $this->assertSame(17, $steelRows[0]['crafts']);          // each Steel recipe produces 1, so crafts == missing
    }

    public function test_qty_greater_than_one_with_shared_intermediate_collapses(): void
    {
        // One order, qty=3, of a recipe that needs Steel via TWO different
        // mid-level sub-recipes. The planner expands each in a separate
        // iteration → previously two rows for Steel, now one.
        $bigArmorOut = Item::create(['name' => 'BigArmor', 'category' => 'Armor', 'chronicle' => $this->chronicle]);
        $cordOut     = Item::create(['name' => 'Cord',     'category' => 'Material', 'chronicle' => $this->chronicle]);
        $plateOut    = Item::create(['name' => 'Plate',    'category' => 'Material', 'chronicle' => $this->chronicle]);

        $steelRecipe = $this->makeRecipe('Steel Recipe', 6001, $this->steel,  [[$this->coal->id, 3]]);
        $cordRecipe  = $this->makeRecipe('Cord Recipe',  6002, $cordOut,      [[$this->steel->id, 2]]);
        $plateRecipe = $this->makeRecipe('Plate Recipe', 6003, $plateOut,     [[$this->steel->id, 4]]);
        $bigArmor    = $this->makeRecipe('Big Armor',    6004, $bigArmorOut,  [[$cordOut->id, 1], [$plateOut->id, 1]]);

        $result = $this->planner->plan($this->cpId, $this->chronicle, [
            ['recipe_id' => $bigArmor->id, 'qty' => 3],
        ]);

        $steelRows = collect($result['sub_crafts'])->filter(fn ($r) => $r['covers_item_id'] === $this->steel->id)->values();
        $this->assertCount(1, $steelRows, 'Steel must collapse into a single sub_craft row.');
        // 3 BigArmor → 3 Cord (each costs 2 Steel = 6) + 3 Plate (each costs 4 Steel = 12) = 18 Steel.
        $this->assertSame(18, $steelRows[0]['covers_missing']);
    }
}
