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
 * Pins the personal stats page: access rules, period clamping, and
 * that each aggregation is correctly scoped to the authenticated user
 * (not the whole CP).
 */
class ProfileStatsTest extends TestCase
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

    public function test_user_with_cp_can_load_stats(): void
    {
        $cp = $this->makeCp();
        $member = $this->makeUser($cp, $this->memberRole);

        $this->actingAs($member)->get(route('profile.stats'))->assertOk();
    }

    public function test_orphan_user_sees_no_cp_state(): void
    {
        // Previously 403'd; now we render the page with `noCp: true` so admins
        // and orphan accounts see a friendly empty state instead of a broken
        // permission error.
        $orphan = User::forceCreate([
            'name' => 'orphan', 'email' => 'orphan@t.l', 'password' => bcrypt('x'),
            'cp_id' => null, 'membership_status' => 'approved',
        ]);
        $orphan->forceFill(['role_id' => $this->memberRole->id])->save();

        $this->actingAs($orphan)->get(route('profile.stats'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('noCp', true)
                ->where('me.cp', null)
                ->where('myRank.position', null)
            );
    }

    public function test_period_clamps_invalid_to_30(): void
    {
        $cp = $this->makeCp();
        $member = $this->makeUser($cp, $this->memberRole);

        $this->actingAs($member)->get(route('profile.stats', ['period' => 999]))
            ->assertInertia(fn ($p) => $p->where('period', 30));
        $this->actingAs($member)->get(route('profile.stats', ['period' => 7]))
            ->assertInertia(fn ($p) => $p->where('period', 7));
    }

    public function test_kpis_count_only_my_reports(): void
    {
        $cp = $this->makeCp();
        $me = $this->makeUser($cp, $this->memberRole);
        $other = $this->makeUser($cp, $this->memberRole);

        // 2 of mine, 1 of someone else
        LootReport::create(['cp_id' => $cp->id, 'requested_by_id' => $me->id,    'event_type' => 'FARM', 'status' => 'confirmed', 'points_per_member' => 0]);
        LootReport::create(['cp_id' => $cp->id, 'requested_by_id' => $me->id,    'event_type' => 'BOSS', 'status' => 'confirmed', 'points_per_member' => 0]);
        LootReport::create(['cp_id' => $cp->id, 'requested_by_id' => $other->id, 'event_type' => 'FARM', 'status' => 'confirmed', 'points_per_member' => 0]);

        $this->actingAs($me)->get(route('profile.stats'))
            ->assertInertia(fn ($p) => $p->where('kpis.reports_submitted', 2));
    }

    public function test_my_rank_is_correct(): void
    {
        $cp = $this->makeCp();
        $u1 = $this->makeUser($cp, $this->memberRole);
        $u2 = $this->makeUser($cp, $this->memberRole);
        $u3 = $this->makeUser($cp, $this->memberRole);

        PointsLog::create(['cp_id' => $cp->id, 'user_id' => $u1->id, 'action_type' => 'BOSS', 'points' => 100, 'adena' => 0]);
        PointsLog::create(['cp_id' => $cp->id, 'user_id' => $u2->id, 'action_type' => 'BOSS', 'points' => 300, 'adena' => 0]);
        PointsLog::create(['cp_id' => $cp->id, 'user_id' => $u3->id, 'action_type' => 'BOSS', 'points' => 200, 'adena' => 0]);

        // u2 has the most points → rank #1; u3 → #2; u1 → #3.
        $this->actingAs($u3)->get(route('profile.stats'))
            ->assertInertia(fn ($p) => $p
                ->where('myRank.position', 2)
                ->where('myRank.total_members', 3)
                ->where('myRank.points', 200)
            );
    }

    public function test_top_items_only_those_awarded_to_me(): void
    {
        $cp = $this->makeCp();
        $me = $this->makeUser($cp, $this->memberRole);
        $other = $this->makeUser($cp, $this->memberRole);

        $item = Item::create([
            'name' => 'MyItem', 'grade' => 'A', 'category' => 'weapon',
            'chronicle' => 'LU4', 'source' => 'test', 'market_price' => 500000, 'hidden' => false,
        ]);

        $report = LootReport::create(['cp_id' => $cp->id, 'requested_by_id' => $me->id, 'event_type' => 'BOSS', 'status' => 'confirmed', 'points_per_member' => 0]);
        LootEntry::create(['loot_report_id' => $report->id, 'item_id' => $item->id, 'awarded_to' => $me->id,    'amount' => 1]);
        LootEntry::create(['loot_report_id' => $report->id, 'item_id' => $item->id, 'awarded_to' => $other->id, 'amount' => 5]);

        $this->actingAs($me)->get(route('profile.stats'))
            ->assertInertia(fn ($p) => $p
                ->has('topItemsReceived', 1)
                ->where('topItemsReceived.0.total_qty', 1)
            );
    }

    public function test_loads_when_user_has_characters_eager_loaded(): void
    {
        // Regression: the controller eager-loads `characters.l2Class`. An
        // earlier version used a non-existent `mainClass` relation, which
        // didn't fail when characters were empty but blew up in prod with
        // RelationNotFoundException for any user that had registered one.
        $cp = $this->makeCp();
        $me = $this->makeUser($cp, $this->memberRole);
        \App\Contexts\Identity\Domain\Models\Character::create([
            'user_id' => $me->id,
            'name' => 'TestHero',
            'race' => 'Human',
            'level' => 76,
        ]);

        $this->actingAs($me)->get(route('profile.stats'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('characters', 1)
                ->where('characters.0.name', 'TestHero')
            );
    }

    public function test_tracker_panel_only_when_enabled(): void
    {
        $cpOff = $this->makeCp(['tracker_enabled' => false]);
        $memberOff = $this->makeUser($cpOff, $this->memberRole);
        $this->actingAs($memberOff)->get(route('profile.stats'))
            ->assertInertia(fn ($p) => $p->where('myTracker', null));

        $cpOn = $this->makeCp(['tracker_enabled' => true, 'tracker_divisor' => 1000, 'tracker_enabled_at' => now()->subDays(2)]);
        $memberOn = $this->makeUser($cpOn, $this->memberRole);
        $this->actingAs($memberOn)->get(route('profile.stats'))
            ->assertInertia(fn ($p) => $p->has('myTracker'));
    }
}
