<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemMarketPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        $this->user = User::create([
            'name' => 'Tester',
            'email' => 'tester@t.l',
            'password' => bcrypt('x'),
            'role_id' => $role->id,
            'membership_status' => 'approved',
        ]);
    }

    public function test_authenticated_user_can_set_market_price(): void
    {
        $item = Item::create(['name' => 'COAL', 'category' => 'EtcItem', 'chronicle' => 'LU4']);

        $this->actingAs($this->user)
            ->patchJson(route('api.items.market-price.update', $item->id), ['price' => 1000])
            ->assertOk()
            ->assertJson(['item_id' => $item->id, 'market_price' => 1000]);

        $item->refresh();
        $this->assertSame(1000, $item->market_price);
        $this->assertSame($this->user->id, $item->market_price_updated_by);
        $this->assertNotNull($item->market_price_updated_at);
    }

    public function test_guest_cannot_set_market_price(): void
    {
        $item = Item::create(['name' => 'COAL', 'category' => 'EtcItem', 'chronicle' => 'LU4']);

        $this->patchJson(route('api.items.market-price.update', $item->id), ['price' => 1000])
            ->assertStatus(401);
    }

    public function test_null_price_clears_metadata(): void
    {
        $item = Item::create([
            'name' => 'COAL', 'category' => 'EtcItem', 'chronicle' => 'LU4',
            'market_price' => 5000,
            'market_price_updated_at' => now(),
            'market_price_updated_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('api.items.market-price.update', $item->id), ['price' => null])
            ->assertOk();

        $item->refresh();
        $this->assertNull($item->market_price);
        $this->assertNull($item->market_price_updated_at);
        $this->assertNull($item->market_price_updated_by);
    }

    public function test_negative_price_is_rejected(): void
    {
        $item = Item::create(['name' => 'COAL', 'category' => 'EtcItem', 'chronicle' => 'LU4']);

        $this->actingAs($this->user)
            ->patchJson(route('api.items.market-price.update', $item->id), ['price' => -1])
            ->assertStatus(422);
    }

    public function test_hidden_items_return_404(): void
    {
        $item = Item::create([
            'name' => 'OBSOLETE', 'category' => 'EtcItem', 'chronicle' => 'LU4', 'hidden' => true,
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('api.items.market-price.update', $item->id), ['price' => 1000])
            ->assertStatus(404);
    }

    public function test_price_is_per_chronicle_not_global(): void
    {
        $lu4 = Item::create(['name' => 'COAL', 'category' => 'EtcItem', 'chronicle' => 'LU4']);
        $c5 = Item::create(['name' => 'COAL', 'category' => 'EtcItem', 'chronicle' => 'C5']);

        $this->actingAs($this->user)
            ->patchJson(route('api.items.market-price.update', $lu4->id), ['price' => 1000])
            ->assertOk();

        $lu4->refresh();
        $c5->refresh();

        $this->assertSame(1000, $lu4->market_price);
        $this->assertNull($c5->market_price);
    }

    public function test_zero_price_is_accepted(): void
    {
        $item = Item::create(['name' => 'COAL', 'category' => 'EtcItem', 'chronicle' => 'LU4']);

        $this->actingAs($this->user)
            ->patchJson(route('api.items.market-price.update', $item->id), ['price' => 0])
            ->assertOk();

        $item->refresh();
        $this->assertSame(0, $item->market_price);
    }

    public function test_overflow_price_is_rejected(): void
    {
        $item = Item::create(['name' => 'COAL', 'category' => 'EtcItem', 'chronicle' => 'LU4']);

        $this->actingAs($this->user)
            ->patchJson(route('api.items.market-price.update', $item->id), ['price' => 10_000_000_000])
            ->assertStatus(422);
    }
}
