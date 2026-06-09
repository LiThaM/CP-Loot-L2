<?php

namespace Tests\Feature;

use App\Contexts\Identity\Application\Services\CharacterCatalogService;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharactersChronicleFilterTest extends TestCase
{
    use RefreshDatabase;

    private Role $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memberRole = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        // Seed the l2_classes table (the test runs RefreshDatabase, so seeders
        // don't run automatically — invoke just the one we need).
        $this->artisan('db:seed', ['--class' => 'L2ClassSeeder', '--force' => true]);
    }

    private function makeUserInCp(?string $chronicle): User
    {
        $cp = $chronicle ? ConstParty::create([
            'name' => 'CP-'.uniqid(),
            'chronicle' => $chronicle,
            'invite_code' => substr(md5(uniqid()), 0, 12),
        ]) : null;

        $u = User::forceCreate([
            'name' => 'u-'.uniqid(),
            'email' => 'u-'.uniqid().'@t.l',
            'password' => bcrypt('x'),
            'cp_id' => $cp?->id,
            'membership_status' => 'approved',
        ]);
        $u->forceFill(['role_id' => $this->memberRole->id])->save();
        return $u;
    }

    public function test_props_are_camel_case_so_vue_receives_them(): void
    {
        // Regression for the snake_case bug: keys must be camelCase to bind
        // to the Vue page's defineProps.
        $user = $this->makeUserInCp('LU4');
        $response = $this->actingAs($user)->get(route('characters.index'));
        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        $this->assertArrayHasKey('l2Classes', $props);
        $this->assertArrayHasKey('l2Races', $props);
        $this->assertArrayHasKey('mainCharacter', $props);
        $this->assertArrayHasKey('cpChronicle', $props);
        $this->assertArrayNotHasKey('l2_classes', $props, 'old snake_case key should be gone');
        $this->assertArrayNotHasKey('l2_races', $props);
        $this->assertArrayNotHasKey('main_character', $props);
    }

    public function test_lu4_returns_all_classes_including_kamael(): void
    {
        $user = $this->makeUserInCp('LU4');
        $response = $this->actingAs($user)->get(route('characters.index'));

        $classes = collect($response->original->getData()['page']['props']['l2Classes']);
        $this->assertGreaterThan(60, $classes->count());
        $this->assertGreaterThan(0, $classes->where('race', 'Kamael')->count());

        $races = $response->original->getData()['page']['props']['l2Races'];
        $this->assertContains('Kamael', $races);
    }

    public function test_il_excludes_kamael(): void
    {
        $user = $this->makeUserInCp('IL');
        $response = $this->actingAs($user)->get(route('characters.index'));

        $classes = collect($response->original->getData()['page']['props']['l2Classes']);
        $this->assertSame(0, $classes->where('race', 'Kamael')->count(), 'Kamael classes should be hidden in IL');
        $this->assertGreaterThan(0, $classes->where('race', 'Human')->count(), 'Human classes should still show');

        $races = $response->original->getData()['page']['props']['l2Races'];
        $this->assertNotContains('Kamael', $races);
    }

    public function test_classic_excludes_kamael(): void
    {
        $user = $this->makeUserInCp('Classic');
        $response = $this->actingAs($user)->get(route('characters.index'));
        $classes = collect($response->original->getData()['page']['props']['l2Classes']);
        $this->assertSame(0, $classes->where('race', 'Kamael')->count());
    }

    public function test_ct1_includes_kamael(): void
    {
        $user = $this->makeUserInCp('CT1');
        $response = $this->actingAs($user)->get(route('characters.index'));
        $classes = collect($response->original->getData()['page']['props']['l2Classes']);
        $this->assertGreaterThan(0, $classes->where('race', 'Kamael')->count(), 'Kamael should appear in CT1+');
    }

    public function test_user_without_cp_gets_no_cp_banner_and_empty_catalog(): void
    {
        $user = $this->makeUserInCp(null);
        $response = $this->actingAs($user)->get(route('characters.index'));

        $props = $response->original->getData()['page']['props'];
        $this->assertTrue($props['noCp']);
        $this->assertSame([], $props['l2Classes']);
        $this->assertSame([], $props['l2Races']);
        $this->assertNull($props['cpChronicle']);
    }
}
