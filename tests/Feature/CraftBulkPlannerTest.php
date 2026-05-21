<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Application\Services\CraftBulkPlannerService;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CraftBulkPlannerTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private ConstParty $cp;

    private Item $coal;
    private Item $leather;
    private Item $cord;
    private Item $craftedLeather;
    private Item $crystalB;
    private Item $ironOre;
    private Item $steel;

    private Recipe $steelRecipe;
    private Recipe $craftedLeatherRecipe;

    private static int $nextExternalId = 100000;

    protected function setUp(): void
    {
        parent::setUp();

        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $this->leader = User::create([
            'name' => 'Leader', 'email' => 'leader@t.l', 'password' => bcrypt('x'),
            'role_id' => $leaderRole->id, 'membership_status' => 'approved',
        ]);
        $this->cp = ConstParty::create([
            'leader_id' => $this->leader->id, 'name' => 'TestCP', 'chronicle' => 'IL', 'is_active' => true,
        ]);
        $this->leader->update(['cp_id' => $this->cp->id]);

        // Items (chronicle IL)
        $this->coal           = $this->makeItem('Coal');
        $this->leather        = $this->makeItem('Leather');
        $this->cord           = $this->makeItem('Cord');
        $this->craftedLeather = $this->makeItem('Crafted Leather');
        $this->crystalB       = $this->makeItem('Crystal (B-Grade)');
        $this->ironOre        = $this->makeItem('Iron Ore');
        $this->steel          = $this->makeItem('Steel');

        // Recipe: Crafted Leather ← Coal x4, Leather x4, Cord x4
        $this->craftedLeatherRecipe = $this->makeRecipe('Recipe: Crafted Leather', $this->craftedLeather, 1, [
            $this->coal->id => 4, $this->leather->id => 4, $this->cord->id => 4,
        ]);

        // Recipe: Steel ← Iron Ore x5, Coal x5
        $this->steelRecipe = $this->makeRecipe('Recipe: Steel', $this->steel, 1, [
            $this->ironOre->id => 5, $this->coal->id => 5,
        ]);
    }

    private function makeItem(string $name, string $chronicle = 'IL'): Item
    {
        return Item::create([
            'name' => $name, 'chronicle' => $chronicle, 'external_id' => self::$nextExternalId++,
        ]);
    }

    private function makeRecipe(string $name, Item $output, int $outputQty, array $materials, string $chronicle = 'IL'): Recipe
    {
        $r = Recipe::create([
            'external_id' => self::$nextExternalId++, 'chronicle' => $chronicle,
            'name' => $name, 'output_item_id' => $output->id, 'output_quantity' => $outputQty,
            'success_rate' => 100,
        ]);
        foreach ($materials as $itemId => $qty) {
            DB::table('recipe_materials')->insert([
                'recipe_id' => $r->id, 'item_id' => $itemId, 'quantity' => $qty,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $r;
    }

    private function addWarehouseStock(int $itemId, int $amount): void
    {
        $report = LootReport::create([
            'cp_id' => $this->cp->id, 'requested_by_id' => $this->leader->id,
            'event_type' => 'FARM', 'status' => 'confirmed',
        ]);
        LootEntry::create(['loot_report_id' => $report->id, 'item_id' => $itemId, 'amount' => $amount]);
    }

    private function plan(array $orders): array
    {
        return app(CraftBulkPlannerService::class)->plan($this->cp->id, 'IL', $orders);
    }

    public function test_single_recipe_no_stock_aggregates_direct_materials(): void
    {
        $res = $this->plan([['recipe_id' => $this->craftedLeatherRecipe->id, 'qty' => 3]]);

        $totals = collect($res['totals'])->keyBy('item_id');
        $this->assertSame(12, $totals[$this->coal->id]['missing']);   // 4 × 3
        $this->assertSame(12, $totals[$this->leather->id]['missing']);
        $this->assertSame(12, $totals[$this->cord->id]['missing']);
        $this->assertSame([], $res['sub_crafts']);
    }

    public function test_two_recipes_sharing_a_material_aggregate_correctly(): void
    {
        // 1 Crafted Leather needs 4 Coal; 2 Steel need 10 Coal → total 14 Coal.
        $res = $this->plan([
            ['recipe_id' => $this->craftedLeatherRecipe->id, 'qty' => 1],
            ['recipe_id' => $this->steelRecipe->id, 'qty' => 2],
        ]);

        $totals = collect($res['totals'])->keyBy('item_id');
        $this->assertSame(14, $totals[$this->coal->id]['missing']);
    }

    public function test_stock_covers_exactly_so_no_missing(): void
    {
        $this->addWarehouseStock($this->coal->id, 4);
        $this->addWarehouseStock($this->leather->id, 4);
        $this->addWarehouseStock($this->cord->id, 4);

        $res = $this->plan([['recipe_id' => $this->craftedLeatherRecipe->id, 'qty' => 1]]);

        // Everything covered → no leaves, no sub-crafts.
        $this->assertSame([], $res['totals']);
        $this->assertSame([], $res['sub_crafts']);
    }

    public function test_partial_stock_on_intermediate_craftable_triggers_sub_craft(): void
    {
        // Plan needs 12 Crafted Leather (parent recipe simulation): create a
        // recipe that just consumes 12 Crafted Leather and have 10 in stock.
        $helmet = $this->makeItem('Helm');
        $helmetRecipe = $this->makeRecipe('Recipe: Helm', $helmet, 1, [
            $this->craftedLeather->id => 12,
        ]);
        $this->addWarehouseStock($this->craftedLeather->id, 10);

        $res = $this->plan([['recipe_id' => $helmetRecipe->id, 'qty' => 1]]);

        // Sub-craft 2 Crafted Leather to cover the gap.
        $this->assertCount(1, $res['sub_crafts']);
        $this->assertSame(2, $res['sub_crafts'][0]['crafts']);
        $this->assertSame($this->craftedLeather->id, $res['sub_crafts'][0]['covers_item_id']);

        // Leaves should now demand 8 Coal / 8 Leather / 8 Cord (2 crafts × 4
        // mats each) — NOT 48.
        $totals = collect($res['totals'])->keyBy('item_id');
        $this->assertSame(8, $totals[$this->coal->id]['missing']);
        $this->assertSame(8, $totals[$this->leather->id]['missing']);
        $this->assertSame(8, $totals[$this->cord->id]['missing']);
    }

    public function test_partial_stock_on_leaf_non_craftable_just_reports_missing(): void
    {
        $this->addWarehouseStock($this->coal->id, 3); // need 4
        $res = $this->plan([['recipe_id' => $this->craftedLeatherRecipe->id, 'qty' => 1]]);

        $coalRow = collect($res['totals'])->firstWhere('item_id', $this->coal->id);
        $this->assertSame(1, $coalRow['missing']);
        $this->assertSame(3, $coalRow['have']);
        $this->assertCount(0, $res['sub_crafts']);
    }

    public function test_endpoint_rejects_recipes_from_foreign_chronicle(): void
    {
        // Make a recipe in another chronicle and try to plan it.
        $otherItem = $this->makeItem('OtherCoal', 'C5');
        $otherRecipe = Recipe::create([
            'external_id' => self::$nextExternalId++, 'chronicle' => 'C5',
            'name' => 'Recipe Other', 'output_item_id' => $otherItem->id, 'output_quantity' => 1,
            'success_rate' => 100,
        ]);

        $this->actingAs($this->leader)
            ->postJson(route('party.craft_bulk.plan'), [
                'orders' => [['recipe_id' => $otherRecipe->id, 'qty' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'chronicle_mismatch');
    }

    public function test_endpoint_requires_leader_role_not_plain_member(): void
    {
        $memberRole = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        $member = User::create([
            'name' => 'Member', 'email' => 'm@t.l', 'password' => bcrypt('x'),
            'role_id' => $memberRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved',
        ]);

        $this->actingAs($member)
            ->postJson(route('party.craft_bulk.plan'), [
                'orders' => [['recipe_id' => $this->craftedLeatherRecipe->id, 'qty' => 1]],
            ])
            ->assertStatus(403);
    }

    public function test_endpoint_requires_a_cp(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $orphan = User::create([
            'name' => 'NoCP', 'email' => 'orphan@t.l', 'password' => bcrypt('x'),
            'role_id' => $adminRole->id, 'membership_status' => 'approved',
        ]);

        $this->actingAs($orphan)
            ->postJson(route('party.craft_bulk.plan'), [
                'orders' => [['recipe_id' => $this->craftedLeatherRecipe->id, 'qty' => 1]],
            ])
            ->assertStatus(403);
    }

    public function test_cycle_in_recipes_is_warned_not_infinite(): void
    {
        // Build cycle: recipe A produces ItemA from ItemB; recipe B produces
        // ItemB from ItemA. Planner must detect and stop.
        $itemA = $this->makeItem('CycleA');
        $itemB = $this->makeItem('CycleB');
        $recipeA = $this->makeRecipe('Recipe A', $itemA, 1, [$itemB->id => 1]);
        $recipeB = $this->makeRecipe('Recipe B', $itemB, 1, [$itemA->id => 1]);

        $res = $this->plan([['recipe_id' => $recipeA->id, 'qty' => 1]]);
        $this->assertNotEmpty($res['warnings']);
    }

    public function test_usages_breakdown_shows_which_outputs_demand_each_material(): void
    {
        $helmet = $this->makeItem('Helm');
        $blade = $this->makeItem('Blade');
        // Helm uses Coal x3; Blade uses Coal x5. Plan 1 of each → 8 Coal
        // total with usages [{for: Blade, qty: 5}, {for: Helm, qty: 3}].
        $helmetRecipe = $this->makeRecipe('Recipe: Helm', $helmet, 1, [$this->coal->id => 3]);
        $bladeRecipe  = $this->makeRecipe('Recipe: Blade', $blade, 1, [$this->coal->id => 5]);

        $res = $this->plan([
            ['recipe_id' => $helmetRecipe->id, 'qty' => 1],
            ['recipe_id' => $bladeRecipe->id, 'qty' => 1],
        ]);

        $coalRow = collect($res['totals'])->firstWhere('item_id', $this->coal->id);
        $this->assertSame(8, $coalRow['need']);
        $this->assertNotEmpty($coalRow['usages']);
        $byLabel = collect($coalRow['usages'])->keyBy('for');
        $this->assertSame(5, $byLabel['Blade']['qty']);
        $this->assertSame(3, $byLabel['Helm']['qty']);
    }

    public function test_missing_always_equals_need_minus_have(): void
    {
        // Partial stock on a leaf — `missing` must strictly equal
        // max(0, need - have) so the UI arithmetic adds up.
        $this->addWarehouseStock($this->coal->id, 3);
        $res = $this->plan([['recipe_id' => $this->craftedLeatherRecipe->id, 'qty' => 2]]);

        foreach ($res['totals'] as $row) {
            $this->assertSame(
                max(0, $row['need'] - $row['have']),
                $row['missing'],
                "missing must equal max(0, need - have) for {$row['name']}"
            );
        }
    }
}
