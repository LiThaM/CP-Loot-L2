<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\Party\Application\Services\TrackerContributionService;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\TrackerContribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the value-based DKP tracker: auto-derivation from LootReport
 * confirmation, manual EVENT entries, idempotency on re-confirmation,
 * the temporal cutoff at `tracker_enabled_at`, and the leader-only gate.
 */
class TrackerTest extends TestCase
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
            'name' => 'TrackerCP-'.uniqid(),
            'chronicle' => 'LU4',
            'invite_code' => substr(md5(uniqid()), 0, 12),
            'tracker_enabled' => true,
            'tracker_divisor' => 1000,
            'tracker_enabled_at' => now()->subDay(),
        ], $overrides));
    }

    private function makeUser(ConstParty $cp, Role $role, array $overrides = []): User
    {
        $defaults = [
            'name' => 'user-'.uniqid(),
            'email' => 'u-'.uniqid().'@t.l',
            'password' => bcrypt('x'),
            'cp_id' => $cp->id,
            'membership_status' => 'approved',
        ];
        $u = User::forceCreate(array_merge($defaults, $overrides));
        $u->forceFill(['role_id' => $role->id])->save();
        return $u;
    }

    private function makeItem(?int $marketPrice = 1000000, ?int $npcSellPrice = null): Item
    {
        return Item::create([
            'name' => 'Item-'.uniqid(),
            'grade' => 'B',
            'category' => 'weapon',
            'chronicle' => 'LU4',
            'source' => 'test',
            'market_price' => $marketPrice,
            'npc_sell_price' => $npcSellPrice,
            'hidden' => false,
        ]);
    }

    private function makeReport(ConstParty $cp, User $reporter, array $attendeeUsers, string $eventType = 'BOSS'): LootReport
    {
        $report = LootReport::create([
            'cp_id' => $cp->id,
            'requested_by_id' => $reporter->id,
            'event_type' => $eventType,
            'points_per_member' => 0,
            'status' => 'confirmed',
        ]);
        foreach ($attendeeUsers as $u) {
            LootReportAttendee::create([
                'loot_report_id' => $report->id,
                'user_id' => $u->id,
                'is_external' => false,
            ]);
        }
        return $report;
    }

    public function test_auto_derive_solo_when_awarded_to_single_member(): void
    {
        $cp = $this->makeCp(['tracker_divisor' => 1000]);
        $leader = $this->makeUser($cp, $this->leaderRole);
        $cp->forceFill(['leader_id' => $leader->id])->save();
        $member = $this->makeUser($cp, $this->memberRole);

        $report = $this->makeReport($cp, $leader, [$member]);
        $item = $this->makeItem(1_000_000);
        $entry = LootEntry::create([
            'loot_report_id' => $report->id,
            'item_id' => $item->id,
            'awarded_to' => $member->id,
            'amount' => 1,
        ]);

        app(TrackerContributionService::class)->recordFromReport($report->fresh()->load('cp', 'attendees'));

        $rows = TrackerContribution::where('cp_id', $cp->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('SOLO', $rows->first()->badge);
        $this->assertEquals(1000.00, (float) $rows->first()->points);
        $this->assertSame($member->id, $rows->first()->user_id);
    }

    public function test_auto_derive_party_split_when_no_award(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);
        $cp->forceFill(['leader_id' => $leader->id])->save();
        $m1 = $this->makeUser($cp, $this->memberRole);
        $m2 = $this->makeUser($cp, $this->memberRole);
        $m3 = $this->makeUser($cp, $this->memberRole);

        $report = $this->makeReport($cp, $leader, [$m1, $m2, $m3]);
        $item = $this->makeItem(900_000);
        LootEntry::create([
            'loot_report_id' => $report->id,
            'item_id' => $item->id,
            'awarded_to' => null,
            'amount' => 1,
        ]);

        app(TrackerContributionService::class)->recordFromReport($report->fresh()->load('cp', 'attendees'));

        $rows = TrackerContribution::where('cp_id', $cp->id)->get();
        $this->assertCount(3, $rows);
        foreach ($rows as $r) {
            $this->assertSame('PARTY/3', $r->badge);
            $this->assertEquals(300.00, (float) $r->points);
        }
    }

    public function test_is_idempotent_on_reconfirmation(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);
        $member = $this->makeUser($cp, $this->memberRole);
        $report = $this->makeReport($cp, $leader, [$member]);
        $item = $this->makeItem(1_000_000);
        LootEntry::create([
            'loot_report_id' => $report->id, 'item_id' => $item->id, 'awarded_to' => $member->id, 'amount' => 1,
        ]);

        $service = app(TrackerContributionService::class);
        $service->recordFromReport($report->fresh()->load('cp', 'attendees'));
        $service->recordFromReport($report->fresh()->load('cp', 'attendees'));

        $this->assertEquals(1, TrackerContribution::where('cp_id', $cp->id)->count());
    }

    public function test_internal_event_types_are_skipped(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);
        $member = $this->makeUser($cp, $this->memberRole);

        foreach (['SELL', 'ASSIGN', 'WAREHOUSE_CRAFT_CONSUME', 'RETURN'] as $eventType) {
            $report = $this->makeReport($cp, $leader, [$member], $eventType);
            $item = $this->makeItem(500_000);
            LootEntry::create([
                'loot_report_id' => $report->id, 'item_id' => $item->id, 'awarded_to' => $member->id, 'amount' => 1,
            ]);
            app(TrackerContributionService::class)->recordFromReport($report->fresh()->load('cp', 'attendees'));
        }

        $this->assertEquals(0, TrackerContribution::where('cp_id', $cp->id)->count());
    }

    public function test_disabled_tracker_does_not_record(): void
    {
        $cp = $this->makeCp(['tracker_enabled' => false]);
        $leader = $this->makeUser($cp, $this->leaderRole);
        $member = $this->makeUser($cp, $this->memberRole);
        $report = $this->makeReport($cp, $leader, [$member]);
        LootEntry::create([
            'loot_report_id' => $report->id, 'item_id' => $this->makeItem(1_000_000)->id, 'awarded_to' => $member->id, 'amount' => 1,
        ]);

        app(TrackerContributionService::class)->recordFromReport($report->fresh()->load('cp', 'attendees'));
        $this->assertEquals(0, TrackerContribution::where('cp_id', $cp->id)->count());
    }

    public function test_reports_from_before_enabled_at_are_ignored(): void
    {
        $cp = $this->makeCp(['tracker_enabled_at' => now()->subHour()]);
        $leader = $this->makeUser($cp, $this->leaderRole);
        $member = $this->makeUser($cp, $this->memberRole);
        $report = $this->makeReport($cp, $leader, [$member]);
        // Force created_at older than tracker_enabled_at. Eloquent doesn't
        // expose timestamps in $fillable, so go through forceFill+save.
        $report->forceFill(['created_at' => now()->subDays(2)])->save();
        LootEntry::create([
            'loot_report_id' => $report->id, 'item_id' => $this->makeItem(1_000_000)->id, 'awarded_to' => $member->id, 'amount' => 1,
        ]);

        app(TrackerContributionService::class)->recordFromReport($report->fresh()->load('cp', 'attendees'));
        $this->assertEquals(0, TrackerContribution::where('cp_id', $cp->id)->count());
    }

    public function test_uses_npc_sell_price_fallback_when_market_price_null(): void
    {
        $cp = $this->makeCp(['tracker_divisor' => 100]);
        $leader = $this->makeUser($cp, $this->leaderRole);
        $member = $this->makeUser($cp, $this->memberRole);
        $report = $this->makeReport($cp, $leader, [$member]);
        $item = $this->makeItem(null, 5000); // market_price null, npc_sell_price 5000
        LootEntry::create([
            'loot_report_id' => $report->id, 'item_id' => $item->id, 'awarded_to' => $member->id, 'amount' => 1,
        ]);

        app(TrackerContributionService::class)->recordFromReport($report->fresh()->load('cp', 'attendees'));

        $row = TrackerContribution::where('cp_id', $cp->id)->first();
        $this->assertNotNull($row);
        $this->assertEquals(50.00, (float) $row->points); // 5000 / 100
    }

    public function test_settings_toggle_persists_enabled_at(): void
    {
        $cp = ConstParty::create([
            'name' => 'NewCp', 'chronicle' => 'LU4', 'invite_code' => 'abcd1234efgh',
            'tracker_enabled' => false, 'tracker_divisor' => 1000,
        ]);
        $leader = $this->makeUser($cp, $this->leaderRole);
        $cp->forceFill(['leader_id' => $leader->id])->save();

        $response = $this->actingAs($leader)->post(route('cp.settings.update'), [
            'name' => $cp->name,
            'server' => null,
            'image_proof_required' => true,
            'tracker_enabled' => true,
            'tracker_divisor' => 500,
        ]);
        $response->assertRedirect();

        $cp->refresh();
        $this->assertTrue($cp->tracker_enabled);
        $this->assertEquals(500, $cp->tracker_divisor);
        $this->assertNotNull($cp->tracker_enabled_at);
    }

    public function test_settings_divisor_out_of_range_is_rejected(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);
        $cp->forceFill(['leader_id' => $leader->id])->save();

        $response = $this->actingAs($leader)->post(route('cp.settings.update'), [
            'name' => $cp->name,
            'image_proof_required' => true,
            'tracker_enabled' => true,
            'tracker_divisor' => 10, // below 50
        ]);
        $response->assertSessionHasErrors('tracker_divisor');
    }

    public function test_manual_contribution_solo_creates_one_row(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);
        $cp->forceFill(['leader_id' => $leader->id])->save();
        $member = $this->makeUser($cp, $this->memberRole);

        $this->actingAs($leader)->post(route('party.tracker.contributions.store'), [
            'user_ids' => [$member->id],
            'description' => 'Manual bonus',
            'points' => 100,
            'is_event' => false,
        ])->assertRedirect();

        $rows = TrackerContribution::where('cp_id', $cp->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('SOLO', $rows->first()->badge);
        $this->assertEquals(100.00, (float) $rows->first()->points);
    }

    public function test_manual_event_bonus_grants_full_points_per_member(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);
        $cp->forceFill(['leader_id' => $leader->id])->save();
        $m1 = $this->makeUser($cp, $this->memberRole);
        $m2 = $this->makeUser($cp, $this->memberRole);
        $m3 = $this->makeUser($cp, $this->memberRole);

        $this->actingAs($leader)->post(route('party.tracker.contributions.store'), [
            'user_ids' => [$m1->id, $m2->id, $m3->id],
            'description' => 'Weekly attendance',
            'points' => 50,
            'is_event' => true,
        ])->assertRedirect();

        $rows = TrackerContribution::where('cp_id', $cp->id)->get();
        $this->assertCount(3, $rows);
        foreach ($rows as $r) {
            $this->assertSame('EVENT', $r->badge);
            $this->assertEquals(50.00, (float) $r->points);
        }
    }

    public function test_non_leader_cannot_add_contribution(): void
    {
        $cp = $this->makeCp();
        $leader = $this->makeUser($cp, $this->leaderRole);
        $cp->forceFill(['leader_id' => $leader->id])->save();
        $member = $this->makeUser($cp, $this->memberRole);

        $response = $this->actingAs($member)->post(route('party.tracker.contributions.store'), [
            'user_ids' => [$member->id],
            'description' => 'Should fail',
            'points' => 10,
        ]);
        $response->assertStatus(403);
    }

    public function test_disabled_tracker_returns_404_on_index(): void
    {
        $cp = $this->makeCp(['tracker_enabled' => false]);
        $member = $this->makeUser($cp, $this->memberRole);

        $this->actingAs($member)->get(route('party.tracker'))->assertStatus(404);
    }
}
