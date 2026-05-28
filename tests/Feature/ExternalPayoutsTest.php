<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalPayoutsTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private ConstParty $cp;
    private LootReportAttendee $external;
    private LootReport $sellReport;

    protected function setUp(): void
    {
        parent::setUp();
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);

        $this->leader = User::forceCreate([
            'name' => 'Leader', 'email' => 'leader@t.l', 'password' => bcrypt('x'),
            'role_id' => $leaderRole->id, 'membership_status' => 'approved',
        ]);
        $this->cp = ConstParty::forceCreate([
            'leader_id' => $this->leader->id, 'name' => 'CP', 'chronicle' => 'IL', 'is_active' => true,
        ]);
        $this->leader->update(['cp_id' => $this->cp->id]);

        $this->sellReport = LootReport::create([
            'cp_id' => $this->cp->id, 'requested_by_id' => $this->leader->id,
            'event_type' => 'SELL', 'status' => 'confirmed', 'cp_share_pct' => 20,
        ]);
        $this->external = LootReportAttendee::create([
            'loot_report_id' => $this->sellReport->id,
            'user_id' => null,
            'external_name' => 'MagoX',
            'is_external' => true,
            'share_adena' => 1_500_000,
        ]);
    }

    public function test_leader_sees_pending_external_payouts(): void
    {
        $response = $this->actingAs($this->leader)->get(route('system.external_payouts.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('System/ExternalPayouts/Index')
            ->where('filter', 'pending')
            ->has('payouts', 1)
            ->where('payouts.0.external_name', 'MagoX')
            ->where('payouts.0.share_adena', 1_500_000)
        );
    }

    public function test_marking_paid_sets_paid_at_and_audits(): void
    {
        $this->actingAs($this->leader)
            ->post(route('system.external_payouts.mark_paid', $this->external->id))
            ->assertRedirect();

        $this->external->refresh();
        $this->assertNotNull($this->external->paid_at);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'LootReportAttendee',
            'entity_id' => $this->external->id,
            'action' => 'EXTERNAL_PAYOUT_PAID',
        ]);
    }

    public function test_member_can_view_external_payouts_in_read_only_mode(): void
    {
        $memberRole = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        $member = User::forceCreate([
            'name' => 'Member', 'email' => 'm@t.l', 'password' => bcrypt('x'),
            'role_id' => $memberRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved',
        ]);

        $this->actingAs($member)
            ->get(route('system.external_payouts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('System/ExternalPayouts/Index')
                ->where('canMarkPaid', false)
                ->has('payouts', 1)
            );
    }

    public function test_member_cannot_mark_paid(): void
    {
        $memberRole = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        $member = User::forceCreate([
            'name' => 'Member2', 'email' => 'm2@t.l', 'password' => bcrypt('x'),
            'role_id' => $memberRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved',
        ]);

        $this->actingAs($member)
            ->post(route('system.external_payouts.mark_paid', $this->external->id))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_external_payouts_page(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::forceCreate([
            'name' => 'Super', 'email' => 'super@t.l', 'password' => bcrypt('x'),
            'role_id' => $adminRole->id, 'membership_status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('system.external_payouts.index'))
            ->assertForbidden();
    }

    public function test_leader_of_other_cp_cannot_mark_paid(): void
    {
        $otherLeaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $otherLeader = User::forceCreate([
            'name' => 'OtherLeader', 'email' => 'other@t.l', 'password' => bcrypt('x'),
            'role_id' => $otherLeaderRole->id, 'membership_status' => 'approved',
        ]);
        $otherCp = ConstParty::forceCreate([
            'leader_id' => $otherLeader->id, 'name' => 'Other', 'chronicle' => 'IL', 'is_active' => true,
        ]);
        $otherLeader->update(['cp_id' => $otherCp->id]);

        $this->actingAs($otherLeader)
            ->post(route('system.external_payouts.mark_paid', $this->external->id))
            ->assertForbidden();

        $this->external->refresh();
        $this->assertNull($this->external->paid_at);
    }

    public function test_paid_filter_returns_only_paid_rows(): void
    {
        $this->external->update(['paid_at' => now()]);

        $this->actingAs($this->leader)
            ->get(route('system.external_payouts.index', ['filter' => 'paid']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('payouts', 1)->where('filter', 'paid'));

        $this->actingAs($this->leader)
            ->get(route('system.external_payouts.index', ['filter' => 'pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('payouts', 0));
    }
}
