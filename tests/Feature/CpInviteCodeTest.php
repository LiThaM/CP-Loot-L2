<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CpInviteCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private User $coLeader;

    private User $accountant;

    private User $member;

    private ConstParty $cp;

    protected function setUp(): void
    {
        parent::setUp();
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $memberRole = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        $this->founder = User::forceCreate(['name' => 'F', 'email' => 'f@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'membership_status' => 'approved']);
        $this->cp = ConstParty::forceCreate(['leader_id' => $this->founder->id, 'name' => 'CP', 'chronicle' => 'IL', 'is_active' => true, 'invite_code' => 'abc123ABC123']);
        $this->founder->update(['cp_id' => $this->cp->id]);

        $this->coLeader = User::forceCreate(['name' => 'CL', 'email' => 'cl@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $this->accountant = User::forceCreate(['name' => 'A', 'email' => 'a@t.l', 'password' => bcrypt('x'), 'role_id' => $accountantRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $this->member = User::forceCreate(['name' => 'M', 'email' => 'm@t.l', 'password' => bcrypt('x'), 'role_id' => $memberRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
    }

    public function test_founder_can_regenerate_invite_code(): void
    {
        $old = $this->cp->invite_code;
        $this->actingAs($this->founder)
            ->post(route('cp.settings.invite-code'))
            ->assertRedirect();
        $this->cp->refresh();
        $this->assertNotSame($old, $this->cp->invite_code);
        $this->assertSame(12, strlen($this->cp->invite_code));
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'ConstParty', 'entity_id' => $this->cp->id, 'action' => 'INVITE_CODE_REGENERATED']);
    }

    public function test_staff_cannot_regenerate_invite_code_even_with_toggle_on(): void
    {
        $this->cp->update(['staff_can_manage_members' => true]);
        foreach ([$this->coLeader, $this->accountant, $this->member] as $actor) {
            $this->actingAs($actor)
                ->post(route('cp.settings.invite-code'))
                ->assertForbidden();
        }
        $this->assertSame('abc123ABC123', $this->cp->fresh()->invite_code);
    }

    public function test_only_founder_can_edit_staff_toggle(): void
    {
        $this->actingAs($this->founder)
            ->post(route('cp.settings.update'), ['name' => 'CP', 'staff_can_manage_members' => true])
            ->assertRedirect();
        $this->assertTrue($this->cp->fresh()->staff_can_manage_members);

        $this->actingAs($this->coLeader)
            ->post(route('cp.settings.update'), ['name' => 'CP', 'staff_can_manage_members' => false])
            ->assertForbidden();
        $this->assertTrue($this->cp->fresh()->staff_can_manage_members);
    }

    public function test_invite_code_is_never_serialized_and_prop_is_gated(): void
    {
        // Plain member: no code anywhere in the payload.
        $this->actingAs($this->member)
            ->get(route('party.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Party/Index')
                ->where('inviteCode', null)
                ->missing('cp.invite_code'));

        // Founder always sees it through the explicit prop.
        $this->actingAs($this->founder)
            ->get(route('party.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('inviteCode', 'abc123ABC123')
                ->where('canRegenerateInvite', true)
                ->missing('cp.invite_code'));

        // Accountant: gated by the toggle.
        $this->actingAs($this->accountant)
            ->get(route('party.index'))
            ->assertInertia(fn (Assert $page) => $page->where('inviteCode', null)->where('canManageMembers', false));

        $this->cp->update(['staff_can_manage_members' => true]);

        $this->actingAs($this->accountant)
            ->get(route('party.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('inviteCode', 'abc123ABC123')
                ->where('canManageMembers', true)
                ->where('canRegenerateInvite', false));
    }
}
