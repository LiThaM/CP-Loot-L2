<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\PointsLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the deep-dive stats page: route access for members of the CP,
 * 403 for users without a CP, period filtering, and that the controller
 * returns the expected aggregations for known fixtures.
 */
class PartyStatsTest extends TestCase
{
    use RefreshDatabase;

    private Role $leaderRole;
    private Role $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $this->memberRole = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
    }

    private function makeCp(array $overrides = []): ConstParty
    {
        return ConstParty::create(array_merge([
            'name' => 'TestCP-'.uniqid(),
            'chronicle' => 'LU4',
            'invite_code' => substr(md5(uniqid()), 0, 12),
        ], $overrides));
    }

    private function makeUser(ConstParty $cp, Role $role): User
    {
        $u = User::forceCreate([
            'name' => 'user-'.uniqid(),
            'email' => 'u-'.uniqid().'@t.l',
            'password' => bcrypt('x'),
            'cp_id' => $cp->id,
            'membership_status' => 'approved',
        ]);
        $u->forceFill(['role_id' => $role->id])->save();
        return $u;
    }

    public function test_member_of_cp_can_load_stats(): void
    {
        $cp = $this->makeCp();
        $member = $this->makeUser($cp, $this->memberRole);

        $response = $this->actingAs($member)->get(route('party.stats'));
        $response->assertOk();
    }

    public function test_user_without_cp_is_forbidden(): void
    {
        $cp = $this->makeCp();
        $orphan = User::forceCreate([
            'name' => 'orphan',
            'email' => 'orphan@t.l',
            'password' => bcrypt('x'),
            'cp_id' => null,
            'membership_status' => 'approved',
        ]);
        $orphan->forceFill(['role_id' => $this->memberRole->id])->save();

        $this->actingAs($orphan)->get(route('party.stats'))->assertStatus(403);
    }

    public function test_period_defaults_to_30_and_clamps_invalid(): void
    {
        $cp = $this->makeCp();
        $member = $this->makeUser($cp, $this->memberRole);

        $r1 = $this->actingAs($member)->get(route('party.stats'));
        $r1->assertOk();
        $r1->assertInertia(fn ($p) => $p->where('period', 30));

        // ?period=999 (not in 7/30/90) → clamps to 30.
        $r2 = $this->actingAs($member)->get(route('party.stats', ['period' => 999]));
        $r2->assertInertia(fn ($p) => $p->where('period', 30));

        $r3 = $this->actingAs($member)->get(route('party.stats', ['period' => 7]));
        $r3->assertInertia(fn ($p) => $p->where('period', 7));
    }

    public function test_kpis_count_confirmed_reports_in_period(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);

        // 2 confirmed reports inside window + 1 pending (should be skipped)
        // + 1 confirmed but old (45 days ago).
        LootReport::create([
            'cp_id' => $cp->id, 'requested_by_id' => $leader->id,
            'event_type' => 'BOSS', 'status' => 'confirmed', 'points_per_member' => 0,
        ]);
        LootReport::create([
            'cp_id' => $cp->id, 'requested_by_id' => $leader->id,
            'event_type' => 'FARM', 'status' => 'confirmed', 'points_per_member' => 0,
        ]);
        LootReport::create([
            'cp_id' => $cp->id, 'requested_by_id' => $leader->id,
            'event_type' => 'BOSS', 'status' => 'pending', 'points_per_member' => 0,
        ]);
        $old = LootReport::create([
            'cp_id' => $cp->id, 'requested_by_id' => $leader->id,
            'event_type' => 'BOSS', 'status' => 'confirmed', 'points_per_member' => 0,
        ]);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->actingAs($leader)->get(route('party.stats', ['period' => 30]))
            ->assertInertia(fn ($p) => $p->where('kpis.reports.value', 2));
    }

    public function test_top_items_skips_internal_event_types(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);
        $item = Item::create([
            'name' => 'TopItem', 'grade' => 'B', 'category' => 'weapon',
            'chronicle' => 'LU4', 'source' => 'test',
            'market_price' => 1000000, 'hidden' => false,
        ]);

        // 1 FARM report with the item (should count) + 1 SELL (should skip)
        $farm = LootReport::create([
            'cp_id' => $cp->id, 'requested_by_id' => $leader->id,
            'event_type' => 'FARM', 'status' => 'confirmed', 'points_per_member' => 0,
        ]);
        LootEntry::create([
            'loot_report_id' => $farm->id, 'item_id' => $item->id,
            'awarded_to' => null, 'amount' => 3,
        ]);
        $sell = LootReport::create([
            'cp_id' => $cp->id, 'requested_by_id' => $leader->id,
            'event_type' => 'SELL', 'status' => 'confirmed', 'points_per_member' => 0,
        ]);
        LootEntry::create([
            'loot_report_id' => $sell->id, 'item_id' => $item->id,
            'awarded_to' => null, 'amount' => 1,
        ]);

        $this->actingAs($leader)->get(route('party.stats'))
            ->assertInertia(fn ($p) => $p
                ->has('topItems', 1)
                ->where('topItems.0.drops', 1)
                ->where('topItems.0.total_qty', 3)
            );
    }

    public function test_adena_flow_aggregates_gain_and_payout(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);

        PointsLog::create([
            'cp_id' => $cp->id, 'user_id' => $leader->id,
            'action_type' => 'ADENA_GAIN', 'points' => 0, 'adena' => 1000,
        ]);
        PointsLog::create([
            'cp_id' => $cp->id, 'user_id' => $leader->id,
            'action_type' => 'ADENA_PAYOUT', 'points' => 0, 'adena' => -400,
        ]);

        $this->actingAs($leader)->get(route('party.stats'))
            ->assertInertia(fn ($p) => $p
                ->where('kpis.adena_in', 1000)
                ->where('kpis.adena_out', 400)
                ->where('kpis.adena_net', 600)
            );
    }

    public function test_tracker_top_only_present_when_enabled(): void
    {
        $cpOff = $this->makeCp(['tracker_enabled' => false]);
        $memberOff = $this->makeUser($cpOff, $this->memberRole);
        $this->actingAs($memberOff)->get(route('party.stats'))
            ->assertInertia(fn ($p) => $p->where('trackerTop', null));

        $cpOn = $this->makeCp(['tracker_enabled' => true, 'tracker_divisor' => 1000, 'tracker_enabled_at' => now()->subDays(2)]);
        $memberOn = $this->makeUser($cpOn, $this->memberRole);
        $this->actingAs($memberOn)->get(route('party.stats'))
            ->assertInertia(fn ($p) => $p->has('trackerTop'));
    }
}
