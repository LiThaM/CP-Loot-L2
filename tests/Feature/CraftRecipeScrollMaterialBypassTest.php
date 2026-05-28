<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Loot\Domain\Models\RecipeMaterial;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CraftRecipeScrollMaterialBypassTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private ConstParty $cp;

    protected function setUp(): void
    {
        parent::setUp();
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $this->leader = User::forceCreate(['name' => 'L', 'email' => 'l@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'membership_status' => 'approved']);
        $this->cp = ConstParty::forceCreate(['leader_id' => $this->leader->id, 'name' => 'CP', 'chronicle' => 'IL', 'is_active' => true]);
        $this->leader->update(['cp_id' => $this->cp->id]);
        Item::create(['name' => 'Adena', 'category' => 'EtcItem', 'chronicle' => 'IL']);
    }

    private function seedStock(Item $item, int $amount): void
    {
        $report = LootReport::create([
            'cp_id' => $this->cp->id, 'requested_by_id' => $this->leader->id,
            'event_type' => 'FARM', 'status' => 'confirmed', 'cp_share_pct' => 100,
        ]);
        LootEntry::create(['loot_report_id' => $report->id, 'item_id' => $item->id, 'amount' => $amount]);
    }

    public function test_material_output_does_not_require_recipe_scroll(): void
    {
        $rawA = Item::create(['name' => 'Leather Pelt', 'category' => 'EtcItem', 'chronicle' => 'IL']);
        $output = Item::create(['name' => 'Crafted Leather', 'category' => 'Material', 'chronicle' => 'IL']);
        $scroll = Item::create(['name' => 'Recipe: Crafted Leather', 'category' => 'Recipe', 'chronicle' => 'IL']);

        $recipe = Recipe::create([
            'external_id' => 8001, 'name' => 'Crafted Leather Recipe', 'chronicle' => 'IL', 'success_rate' => 100,
            'recipe_item_id' => $scroll->id, 'output_item_id' => $output->id, 'output_quantity' => 1,
        ]);
        RecipeMaterial::create(['recipe_id' => $recipe->id, 'item_id' => $rawA->id, 'quantity' => 1]);

        $this->seedStock($rawA, 10);
        // NO scroll stock seeded.

        $response = $this->actingAs($this->leader)
            ->postJson(route('api.recipes.craft', $recipe->id), ['lucky' => true]);
        $response->assertOk();
        $response->assertJson(['ok' => true, 'produced' => true]);

        // Scroll was NOT consumed
        $consumedScroll = LootEntry::where('item_id', $scroll->id)
            ->whereIn('loot_report_id', LootReport::where('event_type', 'WAREHOUSE_CRAFT_CONSUME')->pluck('id'))
            ->sum('amount');
        $this->assertSame(0, (int) $consumedScroll);
    }

    public function test_armor_output_requires_recipe_scroll(): void
    {
        $rawA = Item::create(['name' => 'Crafted Leather', 'category' => 'Material', 'chronicle' => 'IL']);
        $output = Item::create(['name' => 'Avadon Boots', 'category' => 'Armor', 'chronicle' => 'IL']);
        $scroll = Item::create(['name' => 'Recipe: Avadon Boots', 'category' => 'Recipe', 'chronicle' => 'IL']);

        $recipe = Recipe::create([
            'external_id' => 8002, 'name' => 'Avadon Boots Recipe', 'chronicle' => 'IL', 'success_rate' => 100,
            'recipe_item_id' => $scroll->id, 'output_item_id' => $output->id, 'output_quantity' => 1,
        ]);
        RecipeMaterial::create(['recipe_id' => $recipe->id, 'item_id' => $rawA->id, 'quantity' => 1]);

        $this->seedStock($rawA, 10);
        // Sin scroll en stock → debe fallar.
        $this->actingAs($this->leader)
            ->postJson(route('api.recipes.craft', $recipe->id), ['lucky' => true])
            ->assertStatus(422);

        $this->seedStock($scroll, 1);
        $this->actingAs($this->leader)
            ->postJson(route('api.recipes.craft', $recipe->id), ['lucky' => true])
            ->assertOk();

        // Scroll consumed.
        $consumed = LootEntry::where('item_id', $scroll->id)
            ->whereIn('loot_report_id', LootReport::where('event_type', 'WAREHOUSE_CRAFT_CONSUME')->pluck('id'))
            ->sum('amount');
        $this->assertSame(1, (int) $consumed);
    }
}
