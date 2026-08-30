<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveMemberPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private User $coLeader;

    private User $accountant;

    private User $member;

    private User $admin;

    private User $pending;

    private ConstParty $cp;

    private ConstParty $otherCp;

    private User $otherFounder;

    protected function setUp(): void
    {
        parent::setUp();
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $memberRole = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $this->founder = User::forceCreate(['name' => 'F', 'email' => 'f@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'membership_status' => 'approved']);
        $this->cp = ConstParty::forceCreate(['leader_id' => $this->founder->id, 'name' => 'CP', 'chronicle' => 'IL', 'is_active' => true, 'invite_code' => 'abc123ABC123']);
        $this->founder->update(['cp_id' => $this->cp->id]);

        $this->coLeader = User::forceCreate(['name' => 'CL', 'email' => 'cl@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $this->accountant = User::forceCreate(['name' => 'A', 'email' => 'a@t.l', 'password' => bcrypt('x'), 'role_id' => $accountantRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $this->member = User::forceCreate(['name' => 'M', 'email' => 'm@t.l', 'password' => bcrypt('x'), 'role_id' => $memberRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $this->admin = User::forceCreate(['name' => 'Adm', 'email' => 'adm@t.l', 'password' => bcrypt('x'), 'role_id' => $adminRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $this->pending = User::forceCreate(['name' => 'P', 'email' => 'p@t.l', 'password' => bcrypt('x'), 'role_id' => $memberRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'pending']);

        $this->otherFounder = User::forceCreate(['name' => 'OF', 'email' => 'of@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'membership_status' => 'approved']);
        $this->otherCp = ConstParty::forceCreate(['leader_id' => $this->otherFounder->id, 'name' => 'Other', 'chronicle' => 'IL', 'is_active' => true, 'invite_code' => 'xyz789XYZ789']);
        $this->otherFounder->update(['cp_id' => $this->otherCp->id]);
    }

    private function approve(User $actor)
    {
        return $this->actingAs($actor)->patch(route('party.members.approve', $this->pending->id));
    }

    public function test_founder_can_approve_and_audit_log_is_written(): void
    {
        $this->approve($this->founder)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->pending->id, 'membership_status' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'User', 'entity_id' => $this->pending->id, 'action' => 'USER_APPROVED', 'user_id' => $this->founder->id]);
    }

    public function test_co_leader_cannot_approve_with_toggle_off(): void
    {
        $this->approve($this->coLeader)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $this->pending->id, 'membership_status' => 'pending']);
    }

    public function test_co_leader_can_approve_with_toggle_on(): void
    {
        $this->cp->update(['staff_can_manage_members' => true]);
        $this->approve($this->coLeader)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->pending->id, 'membership_status' => 'approved']);
    }

    public function test_accountant_can_approve_with_toggle_on(): void
    {
        $this->cp->update(['staff_can_manage_members' => true]);
        $this->approve($this->accountant)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->pending->id, 'membership_status' => 'approved']);
    }

    public function test_plain_member_cannot_approve_even_with_toggle_on(): void
    {
        $this->cp->update(['staff_can_manage_members' => true]);
        $this->approve($this->member)->assertForbidden();
    }

    public function test_founder_of_another_cp_cannot_approve(): void
    {
        $this->cp->update(['staff_can_manage_members' => true]);
        $this->otherCp->update(['staff_can_manage_members' => true]);
        $this->approve($this->otherFounder)->assertForbidden();
    }

    public function test_admin_of_same_cp_can_approve(): void
    {
        $this->approve($this->admin)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->pending->id, 'membership_status' => 'approved']);
    }
}
