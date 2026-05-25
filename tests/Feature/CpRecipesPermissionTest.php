<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\CpRecipe;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CpRecipesPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private User $accountant;
    private User $member;
    private User $admin;
    private ConstParty $cp;
    private Recipe $recipe;
    private CpRecipe $pinned;

    protected function setUp(): void
    {
        parent::setUp();
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $memberRole = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $this->leader = User::create(['name' => 'L', 'email' => 'l@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'membership_status' => 'approved']);
        $this->cp = ConstParty::create(['leader_id' => $this->leader->id, 'name' => 'CP', 'chronicle' => 'IL', 'is_active' => true]);
        $this->leader->update(['cp_id' => $this->cp->id]);

        $this->accountant = User::create(['name' => 'A', 'email' => 'a@t.l', 'password' => bcrypt('x'), 'role_id' => $accountantRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $this->member = User::create(['name' => 'M', 'email' => 'm@t.l', 'password' => bcrypt('x'), 'role_id' => $memberRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $this->admin = User::create(['name' => 'Adm', 'email' => 'adm@t.l', 'password' => bcrypt('x'), 'role_id' => $adminRole->id, 'membership_status' => 'approved']);

        $this->recipe = Recipe::create(['external_id' => 9001, 'name' => 'R', 'chronicle' => 'IL', 'success_rate' => 100]);
        $this->pinned = CpRecipe::create(['cp_id' => $this->cp->id, 'recipe_id' => $this->recipe->id, 'priority' => 1, 'created_by' => $this->leader->id]);
    }

    public function test_accountant_can_pin_recipe(): void
    {
        $other = Recipe::create(['external_id' => 9002, 'name' => 'R2', 'chronicle' => 'IL', 'success_rate' => 100]);
        $this->actingAs($this->accountant)
            ->post(route('cp.recipes.store'), ['recipe_id' => $other->id])
            ->assertRedirect();
        $this->assertDatabaseHas('cp_recipes', ['recipe_id' => $other->id, 'cp_id' => $this->cp->id]);
    }

    public function test_accountant_can_destroy_pinned_recipe(): void
    {
        $this->actingAs($this->accountant)
            ->delete(route('cp.recipes.destroy', $this->pinned->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('cp_recipes', ['id' => $this->pinned->id]);
    }

    public function test_admin_can_destroy_pinned_recipe_in_other_cp(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('cp.recipes.destroy', $this->pinned->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('cp_recipes', ['id' => $this->pinned->id]);
    }

    public function test_member_cannot_pin_recipe(): void
    {
        $other = Recipe::create(['external_id' => 9003, 'name' => 'R3', 'chronicle' => 'IL', 'success_rate' => 100]);
        $this->actingAs($this->member)
            ->post(route('cp.recipes.store'), ['recipe_id' => $other->id])
            ->assertForbidden();
    }
}
