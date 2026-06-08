<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LootReportAttendeesTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private ConstParty $cp;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);

        $this->leader = User::forceCreate([
            'name' => 'Leader', 'email' => 'leader@t.l', 'password' => bcrypt('x'),
            'role_id' => $leaderRole->id, 'membership_status' => 'approved',
        ]);
        $this->cp = ConstParty::forceCreate([
            'leader_id' => $this->leader->id, 'name' => 'CP', 'chronicle' => 'IL', 'is_active' => true,
        ]);
        $this->leader->update(['cp_id' => $this->cp->id]);
        $this->item = Item::create(['name' => 'Loot Item', 'category' => 'Weapon']);
    }

    private function member(string $name): User
    {
        $role = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        return User::forceCreate([
            'name' => $name, 'email' => strtolower($name).'@t.l', 'password' => bcrypt('x'),
            'role_id' => $role->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved',
        ]);
    }

    public function test_store_with_external_attendees_creates_rows_flagged_external(): void
    {
        $alice = $this->member('Alice');

        $this->actingAs($this->leader)->post(route('loot.report.store'), [
            'event_type' => 'FARM',
            'items' => [['item_id' => $this->item->id, 'amount' => 1]],
            'attendees' => [
                ['user_id' => $alice->id],
                ['external_name' => 'Mago1'],
                ['external_name' => 'Mago2'],
            ],
            'cp_share_pct' => 25,
        ])->assertRedirect();

        $report = LootReport::firstOrFail();
        $this->assertSame(25, $report->cp_share_pct);
        $attendees = $report->attendees;
        $this->assertCount(3, $attendees);
        $this->assertSame(1, $attendees->where('is_external', false)->count());
        $this->assertSame(2, $attendees->where('is_external', true)->count());
        $this->assertEqualsCanonicalizing(
            ['Mago1', 'Mago2'],
            $attendees->where('is_external', true)->pluck('external_name')->all()
        );
    }

    public function test_user_id_from_other_cp_is_demoted_to_external(): void
    {
        // Another CP with one of its members
        $other = ConstParty::forceCreate([
            'leader_id' => $this->leader->id, 'name' => 'OtherCP', 'chronicle' => 'IL', 'is_active' => true,
        ]);
        $foreigner = User::forceCreate([
            'name' => 'Foreigner', 'email' => 'foreigner@t.l', 'password' => bcrypt('x'),
            'role_id' => Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member'])->id,
            'cp_id' => $other->id, 'membership_status' => 'approved',
        ]);

        $this->actingAs($this->leader)->post(route('loot.report.store'), [
            'event_type' => 'FARM',
            'items' => [['item_id' => $this->item->id, 'amount' => 1]],
            'attendees' => [
                ['user_id' => $foreigner->id], // not in MY cp → must be marked external
            ],
        ])->assertRedirect();

        $att = LootReportAttendee::firstOrFail();
        $this->assertTrue($att->is_external);
        $this->assertNull($att->user_id);
        $this->assertSame('Foreigner', $att->external_name);
    }

    public function test_attendee_with_both_user_id_and_external_name_is_rejected(): void
    {
        $alice = $this->member('Alice');
        $this->actingAs($this->leader)->from('/dashboard')->post(route('loot.report.store'), [
            'event_type' => 'FARM',
            'items' => [['item_id' => $this->item->id, 'amount' => 1]],
            'attendees' => [
                ['user_id' => $alice->id, 'external_name' => 'AliasA'],
            ],
        ])->assertSessionHasErrors('attendees');
    }

    public function test_legacy_recipient_ids_still_works_and_creates_attendees(): void
    {
        $alice = $this->member('Alice');
        $bob = $this->member('Bob');

        $this->actingAs($this->leader)->post(route('loot.report.store'), [
            'event_type' => 'FARM',
            'items' => [['item_id' => $this->item->id, 'amount' => 1]],
            'recipient_ids' => [$alice->id, $bob->id],
            'adena_distribution' => 'cp',
        ])->assertRedirect();

        $report = LootReport::firstOrFail();
        $this->assertSame(100, $report->cp_share_pct); // mapped from adena_distribution=cp
        $this->assertCount(2, $report->attendees);
        $this->assertSame(0, $report->attendees->where('is_external', true)->count());
    }

    /**
     * Regression — discovered 2026-06-09. When the leader confirmed a
     * FARM report, externals were dropped from `loot_report_attendees`
     * because `LootController::index` didn't eager-load the `attendees`
     * relation. Vue's resolve modal built `resolveForm.attendees` from
     * `report.attendees` (empty array), the form submitted `attendees:[]`
     * and the backend rebuilt the attendee list using only `recipient_ids`,
     * wiping every external row.
     */
    public function test_pending_loot_payload_includes_attendees_so_externals_survive_confirm(): void
    {
        $member = $this->member('M1');
        $report = LootReport::create([
            'cp_id' => $this->cp->id,
            'requested_by_id' => $member->id,
            'event_type' => 'FARM',
            'status' => 'pending',
            'points_per_member' => 0,
        ]);
        LootReportAttendee::create([
            'loot_report_id' => $report->id,
            'user_id' => $member->id, 'is_external' => false,
        ]);
        LootReportAttendee::create([
            'loot_report_id' => $report->id,
            'user_id' => null, 'external_name' => 'ExternoUno', 'is_external' => true,
        ]);

        $response = $this->actingAs($this->leader)->get(route('loot.index'));
        $response->assertOk();

        $payload = $response->original->getData()['page']['props'] ?? [];
        $pending = $payload['pendingLoot'] ?? [];
        $this->assertNotEmpty($pending, 'pendingLoot is missing from props');

        $first = (array) $pending[0];
        $this->assertArrayHasKey('attendees', $first, 'attendees should be eager-loaded on pending reports');
        $attendees = collect($first['attendees']);
        $this->assertGreaterThan(0, $attendees->where('is_external', true)->count(), 'external attendees should be present in pending payload');
    }
}
