<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemCpsIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Super', 'email' => 'super@t.l', 'password' => bcrypt('x'),
            'role_id' => $adminRole->id, 'membership_status' => 'approved',
        ]);
    }

    private function makeCp(string $name, array $overrides = []): ConstParty
    {
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $leader = User::create([
            'name' => $name.'-leader', 'email' => strtolower($name).'-leader@t.l', 'password' => bcrypt('x'),
            'role_id' => $leaderRole->id, 'membership_status' => 'approved',
        ]);
        $cp = ConstParty::create(array_merge([
            'leader_id' => $leader->id, 'name' => $name, 'chronicle' => 'IL', 'is_active' => true,
        ], $overrides));
        $leader->update(['cp_id' => $cp->id]);
        return $cp;
    }

    public function test_admin_can_load_index_with_cps_payload(): void
    {
        $this->makeCp('Alpha');
        $this->makeCp('Beta', ['is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('system.cps.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('System/Cps/Index')
                ->has('cps', 2)
                ->has('chronicles')
            );
    }

    public function test_non_admin_is_forbidden(): void
    {
        $cp = $this->makeCp('Alpha');
        $leader = User::where('cp_id', $cp->id)->firstOrFail();

        $this->actingAs($leader)
            ->get(route('system.cps.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_cp(): void
    {
        $cp = $this->makeCp('OldName');

        $this->actingAs($this->admin)
            ->patch(route('system.cps.update', $cp->id), [
                'name' => 'NewName',
                'server' => 'Bartz',
                'chronicle' => 'HB',
            ])->assertRedirect();

        $cp->refresh();
        $this->assertSame('NewName', $cp->name);
        $this->assertSame('Bartz', $cp->server);
        $this->assertSame('HB', $cp->chronicle);
    }

    public function test_admin_update_rejects_duplicate_name(): void
    {
        $cp1 = $this->makeCp('Alpha');
        $cp2 = $this->makeCp('Beta');

        $this->actingAs($this->admin)
            ->from('/system/cps')
            ->patch(route('system.cps.update', $cp2->id), [
                'name' => 'Alpha',
                'chronicle' => 'IL',
            ])->assertSessionHasErrors('name');
    }
}
