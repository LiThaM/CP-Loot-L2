<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $leaderRole;
    private Role $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $this->memberRole = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
    }

    private function makeUser(string $name, Role $role, ?ConstParty $cp = null): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower($name).'@t.l',
            'password' => bcrypt('x'),
            'role_id' => $role->id,
            'cp_id' => $cp?->id,
            'membership_status' => 'approved',
        ]);
    }

    private function makeCpWithFounder(string $name): array
    {
        $cp = ConstParty::create(['leader_id' => null, 'name' => $name, 'chronicle' => 'IL', 'is_active' => true]);
        $founder = $this->makeUser($name.'-leader', $this->leaderRole, $cp);
        $cp->update(['leader_id' => $founder->id]);
        return [$cp, $founder];
    }

    public function test_founder_cannot_promote_self_to_admin(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Alpha');

        $this->actingAs($founder)
            ->from('/system/users')
            ->patch(route('system.users.update', $founder->id), [
                'role_id' => $this->adminRole->id,
            ])
            ->assertForbidden();

        $founder->refresh();
        $this->assertSame($this->leaderRole->id, $founder->role_id);
    }

    public function test_founder_cannot_promote_member_to_admin(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Alpha');
        $member = $this->makeUser('Charlie', $this->memberRole, $cp);

        $this->actingAs($founder)
            ->from('/system/users')
            ->patch(route('system.users.update', $member->id), [
                'role_id' => $this->adminRole->id,
            ])
            ->assertForbidden();

        $member->refresh();
        $this->assertSame($this->memberRole->id, $member->role_id);
    }

    public function test_co_leader_cannot_promote_to_admin(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Alpha');
        $coLeader = $this->makeUser('Bob', $this->leaderRole, $cp);
        $member = $this->makeUser('Charlie', $this->memberRole, $cp);

        $this->actingAs($coLeader)
            ->from('/system/users')
            ->patch(route('system.users.update', $member->id), [
                'role_id' => $this->adminRole->id,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_promote_other_user_to_admin(): void
    {
        $admin = $this->makeUser('Super', $this->adminRole);
        [$cp, $founder] = $this->makeCpWithFounder('Alpha');

        $this->actingAs($admin)
            ->patch(route('system.users.update', $founder->id), [
                'role_id' => $this->adminRole->id,
            ])
            ->assertRedirect();

        $founder->refresh();
        $this->assertSame($this->adminRole->id, $founder->role_id);
    }

    public function test_nobody_can_change_their_own_role(): void
    {
        $admin = $this->makeUser('Super', $this->adminRole);

        $this->actingAs($admin)
            ->from('/system/users')
            ->patch(route('system.users.update', $admin->id), [
                'role_id' => $this->memberRole->id,
            ])
            ->assertForbidden();

        $admin->refresh();
        $this->assertSame($this->adminRole->id, $admin->role_id);
    }

    public function test_non_admin_cannot_demote_an_admin(): void
    {
        [$cp, $founder] = $this->makeCpWithFounder('Alpha');
        // Crear un admin que también está dentro de la CP (caso límite)
        $admin = $this->makeUser('OtherAdmin', $this->adminRole, $cp);

        $this->actingAs($founder)
            ->from('/system/users')
            ->patch(route('system.users.update', $admin->id), [
                'role_id' => $this->memberRole->id,
            ])
            ->assertForbidden();

        $admin->refresh();
        $this->assertSame($this->adminRole->id, $admin->role_id);
    }
}
