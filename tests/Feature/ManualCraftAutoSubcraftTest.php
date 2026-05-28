<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Application\Controllers\CraftingController;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Loot\Domain\Models\RecipeMaterial;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualCraftAutoSubcraftTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private ConstParty $cp;
    private Item $rawA;
    private Item $intermediate;
    private Item $finalItem;

    protected function setUp(): void
    {
        parent::setUp();
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $this->leader = User::forceCreate(['name' => 'L', 'email' => 'l@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'membership_status' => 'approved']);
        $this->cp = ConstParty::forceCreate(['leader_id' => $this->leader->id, 'name' => 'CP', 'chronicle' => 'IL', 'is_active' => true]);
        $this->leader->update(['cp_id' => $this->cp->id]);

        Item::create(['name' => 'Adena', 'category' => 'EtcItem', 'chronicle' => 'IL']);
        $this->rawA = Item::create(['name' => 'Leather Pelt', 'category' => 'EtcItem', 'chronicle' => 'IL']);
        $this->intermediate = Item::create(['name' => 'Crafted Leather', 'category' => 'Material', 'chronicle' => 'IL']);
        $this->finalItem = Item::create(['name' => 'Avadon Boots', 'category' => 'Armor', 'chronicle' => 'IL']);
    }

    private function seedStock(Item $item, int $amount): void
    {
        $report = LootReport::create([
            'cp_id' => $this->cp->id, 'requested_by_id' => $this->leader->id,
            'event_type' => 'FARM', 'status' => 'confirmed', 'cp_share_pct' => 100,
        ]);
        LootEntry::create(['loot_report_id' => $report->id, 'item_id' => $item->id, 'amount' => $amount]);
    }

    public function test_auto_crafts_missing_intermediate_when_raws_present(): void
    {
        // Recipe for intermediate
        $subRecipe = Recipe::create([
            'external_id' => 7001, 'name' => 'Crafted Leather Recipe', 'chronicle' => 'IL', 'success_rate' => 100,
            'output_item_id' => $this->intermediate->id, 'output_quantity' => 1,
        ]);
        RecipeMaterial::create(['recipe_id' => $subRecipe->id, 'item_id' => $this->rawA->id, 'quantity' => 5]);

        // Final recipe needs the intermediate (no scroll for simplicity).
        $finalRecipe = Recipe::create([
            'external_id' => 7002, 'name' => 'Avadon Boots Recipe', 'chronicle' => 'IL', 'success_rate' => 100,
            'output_item_id' => $this->finalItem->id, 'output_quantity' => 1,
        ]);
        RecipeMaterial::create(['recipe_id' => $finalRecipe->id, 'item_id' => $this->intermediate->id, 'quantity' => 3]);

        // Stock: 0 intermediates, but 15 raws (3 intermediates × 5 raws each).
        $this->seedStock($this->rawA, 15);

        $response = $this->actingAs($this->leader)
            ->postJson(route('api.recipes.craft', $finalRecipe->id), ['lucky' => true])
            ->assertOk();

        $data = $response->json();
        $this->assertTrue($data['ok']);
        $this->assertTrue($data['produced']);
        $this->assertNotEmpty($data['auto_crafted']);
        $this->assertSame($this->intermediate->id, $data['auto_crafted'][0]['item_id']);
        $this->assertSame(3, $data['auto_crafted'][0]['amount']);
        $this->assertNotEmpty($data['produced_items']);
        $this->assertSame($this->finalItem->id, $data['produced_items'][0]['item_id']);

        // Consumed report exists and references the raw materials (not the intermediate, since we never had any).
        $consumedRaw = LootEntry::where('item_id', $this->rawA->id)
            ->whereIn('loot_report_id', LootReport::where('event_type', 'WAREHOUSE_CRAFT_CONSUME')->pluck('id'))
            ->sum('amount');
        $this->assertSame(15, (int) $consumedRaw);
    }

    public function test_simulate_returns_null_when_leaf_unreachable(): void
    {
        $finalRecipe = Recipe::create([
            'external_id' => 7003, 'name' => 'Avadon Boots Recipe', 'chronicle' => 'IL', 'success_rate' => 100,
            'output_item_id' => $this->finalItem->id, 'output_quantity' => 1,
        ]);
        RecipeMaterial::create(['recipe_id' => $finalRecipe->id, 'item_id' => $this->intermediate->id, 'quantity' => 3]);
        // No sub-recipe for intermediate, no stock → simulate returns null.

        $finalRecipe->load('materials');
        $plan = CraftingController::simulate($finalRecipe, [], []);
        $this->assertNull($plan);
    }
}
