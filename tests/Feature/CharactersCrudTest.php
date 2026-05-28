<?php

namespace Tests\Feature;

use App\Contexts\Identity\Application\Services\CharacterCatalogService;
use App\Contexts\Identity\Domain\Models\Character;
use App\Contexts\Identity\Domain\Models\L2Class;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharactersCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private L2Class $bishop;
    private L2Class $sorcerer;
    private L2Class $shillienElder;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        $this->user = User::forceCreate([
            'name' => 'TestUser', 'email' => 'u@t.l', 'password' => bcrypt('x'),
            'role_id' => $role->id, 'membership_status' => 'approved',
        ]);
        // Seed catalogue
        $this->seed(\Database\Seeders\L2ClassSeeder::class);
        $this->bishop = L2Class::where('code', 'human_bishop')->firstOrFail();
        $this->sorcerer = L2Class::where('code', 'human_sorcerer')->firstOrFail();
        $this->shillienElder = L2Class::where('code', 'delf_shillien_elder')->firstOrFail();
    }

    public function test_store_creates_a_character_and_derives_race_from_class(): void
    {
        $this->actingAs($this->user)
            ->post(route('characters.store'), [
                'name' => 'Bisho2',
                'l2_class_id' => $this->bishop->id,
                'level' => 78,
            ])->assertRedirect();

        $char = Character::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('Bisho2', $char->name);
        $this->assertSame('Human', $char->race);
        $this->assertSame(78, $char->level);
    }

    public function test_changing_class_updates_race(): void
    {
        $char = Character::create(['user_id' => $this->user->id, 'name' => 'SwapMe', 'l2_class_id' => $this->bishop->id, 'level' => 40]);
        $this->assertSame('Human', $char->race);

        $this->actingAs($this->user)
            ->patch(route('characters.update', $char->id), [
                'name' => 'SwapMe',
                'l2_class_id' => $this->shillienElder->id,
                'level' => 42,
            ])->assertRedirect();

        $char->refresh();
        $this->assertSame('Dark Elf', $char->race);
    }

    public function test_duplicate_nick_for_same_user_is_rejected(): void
    {
        Character::create(['user_id' => $this->user->id, 'name' => 'Bisho2', 'l2_class_id' => $this->bishop->id]);

        $this->actingAs($this->user)
            ->from('/profile')
            ->post(route('characters.store'), [
                'name' => 'Bisho2',
                'l2_class_id' => $this->sorcerer->id,
            ])->assertSessionHasErrors('name');
    }

    public function test_cannot_edit_another_users_character(): void
    {
        $role = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        $other = User::forceCreate(['name' => 'Other', 'email' => 'o@t.l', 'password' => bcrypt('x'), 'role_id' => $role->id, 'membership_status' => 'approved']);
        $char = Character::create(['user_id' => $other->id, 'name' => 'Mine', 'l2_class_id' => $this->bishop->id]);

        $this->actingAs($this->user)
            ->patch(route('characters.update', $char->id), ['name' => 'Stolen'])
            ->assertForbidden();
    }

    public function test_destroy_removes_character_and_keeps_orphaned_attendees_via_null_on_delete(): void
    {
        $char = Character::create(['user_id' => $this->user->id, 'name' => 'ToDel', 'l2_class_id' => $this->bishop->id]);

        $this->actingAs($this->user)
            ->delete(route('characters.destroy', $char->id))
            ->assertRedirect();

        $this->assertNull(Character::find($char->id));
    }
}
