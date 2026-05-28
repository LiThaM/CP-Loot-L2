<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\PointsLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseSellSplitTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private ConstParty $cp;
    private Item $item;
    private Item $adena;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $memberRole = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);

        $this->leader = User::forceCreate([
            'name' => 'Leader', 'email' => 'leader@test.local', 'password' => bcrypt('x'),
            'role_id' => $leaderRole->id, 'membership_status' => 'approved',
        ]);
        $this->cp = ConstParty::forceCreate([
            'leader_id' => $this->leader->id, 'name' => 'TestCP', 'chronicle' => 'IL', 'is_active' => true,
        ]);
        $this->leader->update(['cp_id' => $this->cp->id]);

        $this->item = Item::create(['name' => 'Top D Weapon', 'category' => 'Weapon']);
        $this->adena = Item::create(['name' => 'Adena', 'category' => 'Material']);
    }

    private function makeMember(string $name): User
    {
        $role = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        return User::forceCreate([
            'name' => $name, 'email' => strtolower($name).'@t.l', 'password' => bcrypt('x'),
            'role_id' => $role->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved',
        ]);
    }

    private function makeFarmReport(array $attendees = [], int $cpSharePct = 0): LootReport
    {
        $report = LootReport::create([
            'cp_id' => $this->cp->id,
            'requested_by_id' => $this->leader->id,
            'event_type' => 'FARM',
            'status' => 'confirmed',
            'cp_share_pct' => $cpSharePct,
        ]);
        LootEntry::create([
            'loot_report_id' => $report->id, 'item_id' => $this->item->id, 'amount' => 100,
        ]);
        foreach ($attendees as $att) {
            LootReportAttendee::create([
                'loot_report_id' => $report->id,
                'user_id' => $att['user_id'] ?? null,
                'external_name' => $att['external_name'] ?? null,
                'is_external' => isset($att['external_name']),
            ]);
        }
        return $report;
    }

    private function pngFile(): UploadedFile
    {
        $img = imagecreatetruecolor(8, 8);
        $path = tempnam(sys_get_temp_dir(), 'sellproof_').'.png';
        imagepng($img, $path);
        imagedestroy($img);
        return new UploadedFile($path, 'proof.png', 'image/png', null, true);
    }

    public function test_split_20_pct_to_cp_and_rest_evenly_among_attendees(): void
    {
        $m1 = $this->makeMember('Alice');
        $m2 = $this->makeMember('Bob');

        $farm = $this->makeFarmReport([
            ['user_id' => $m1->id],
            ['user_id' => $m2->id],
            ['external_name' => 'GuestX'],
            ['external_name' => 'GuestY'],
        ]);

        $response = $this->actingAs($this->leader)->post(route('warehouse.sell'), [
            'item_id' => $this->item->id,
            'amount' => 10,
            'unit_price' => 1_000_000,
            'source_report_id' => $farm->id,
            'cp_share_pct' => 20,
            'image_proof' => $this->pngFile(),
        ]);
        $response->assertRedirect();

        // total = 10 * 1_000_000 = 10_000_000
        // cpShare = 2_000_000, toAttendees = 8_000_000, per = 2_000_000, leftover = 0
        $this->assertDatabaseHas('points_logs', [
            'user_id' => $m1->id, 'action_type' => 'ADENA_GAIN', 'adena' => 2_000_000,
        ]);
        $this->assertDatabaseHas('points_logs', [
            'user_id' => $m2->id, 'adena' => 2_000_000,
        ]);

        // Externals: rows on the SELL report with share_adena, no PointsLog.
        $sellReport = LootReport::where('event_type', 'SELL')->latest('id')->firstOrFail();
        $externals = LootReportAttendee::where('loot_report_id', $sellReport->id)
            ->where('is_external', true)->get();
        $this->assertCount(2, $externals);
        foreach ($externals as $ext) {
            $this->assertSame(2_000_000, (int) $ext->share_adena);
            $this->assertNull($ext->paid_at);
        }
        $this->assertSame(0, PointsLog::whereIn('user_id', [null])->count());
    }

    public function test_split_100_pct_to_cp_does_not_pay_attendees(): void
    {
        $m1 = $this->makeMember('Alice');
        $farm = $this->makeFarmReport([['user_id' => $m1->id]]);

        $this->actingAs($this->leader)->post(route('warehouse.sell'), [
            'item_id' => $this->item->id,
            'amount' => 5,
            'unit_price' => 200_000,
            'source_report_id' => $farm->id,
            'cp_share_pct' => 100,
            'image_proof' => $this->pngFile(),
        ])->assertRedirect();

        $this->assertSame(0, PointsLog::where('user_id', $m1->id)->count());
        $sellReport = LootReport::where('event_type', 'SELL')->latest('id')->firstOrFail();
        // Member attendee still gets a row, but with share_adena=0 (everything went to CP).
        $row = LootReportAttendee::where('loot_report_id', $sellReport->id)
            ->where('user_id', $m1->id)->firstOrFail();
        $this->assertSame(0, (int) $row->share_adena);
    }

    public function test_rounding_remainder_lands_in_cp_fund_not_lost(): void
    {
        $m1 = $this->makeMember('Alice');
        $m2 = $this->makeMember('Bob');
        $m3 = $this->makeMember('Carol');
        $farm = $this->makeFarmReport([
            ['user_id' => $m1->id], ['user_id' => $m2->id], ['user_id' => $m3->id],
        ]);

        // total = 1_000_000, cpShare = 0, perAtt = floor(1_000_000 / 3) = 333_333
        // leftover = 1_000_000 - 999_999 = 1 (rounding rest goes to CP fund)
        $this->actingAs($this->leader)->post(route('warehouse.sell'), [
            'item_id' => $this->item->id,
            'amount' => 1,
            'unit_price' => 1_000_000,
            'source_report_id' => $farm->id,
            'cp_share_pct' => 0,
            'image_proof' => $this->pngFile(),
        ])->assertRedirect();

        foreach ([$m1, $m2, $m3] as $m) {
            $this->assertDatabaseHas('points_logs', ['user_id' => $m->id, 'adena' => 333_333]);
        }

        $sellReport = LootReport::where('event_type', 'SELL')->latest('id')->firstOrFail();
        $audit = \App\Contexts\System\Domain\Models\AuditLog::where('entity_id', $sellReport->id)
            ->where('action', 'WAREHOUSE_SELL')->firstOrFail();
        $nv = $audit->new_values;
        $this->assertSame(1, (int) $nv['cp_share']);
    }

    public function test_invalid_source_report_id_is_rejected(): void
    {
        $this->actingAs($this->leader)->from('/party')->post(route('warehouse.sell'), [
            'item_id' => $this->item->id,
            'amount' => 1,
            'unit_price' => 1,
            'source_report_id' => 99999,
            'cp_share_pct' => 0,
            'image_proof' => $this->pngFile(),
        ])->assertSessionHasErrors('source_report_id');
    }

    public function test_source_without_the_item_is_rejected(): void
    {
        // Create a farm session with a different item, then try to sell our item against it.
        $otherItem = Item::create(['name' => 'Other thing']);
        $report = LootReport::create([
            'cp_id' => $this->cp->id, 'requested_by_id' => $this->leader->id,
            'event_type' => 'FARM', 'status' => 'confirmed', 'cp_share_pct' => 0,
        ]);
        LootEntry::create(['loot_report_id' => $report->id, 'item_id' => $otherItem->id, 'amount' => 1]);
        // Also seed the sell-item stock from a different farm so the stock check doesn't preempt the source check.
        $stockFarm = LootReport::create([
            'cp_id' => $this->cp->id, 'requested_by_id' => $this->leader->id,
            'event_type' => 'FARM', 'status' => 'confirmed', 'cp_share_pct' => 0,
        ]);
        LootEntry::create(['loot_report_id' => $stockFarm->id, 'item_id' => $this->item->id, 'amount' => 10]);

        $this->actingAs($this->leader)->from('/party')->post(route('warehouse.sell'), [
            'item_id' => $this->item->id,
            'amount' => 1,
            'unit_price' => 1,
            'source_report_id' => $report->id,
            'cp_share_pct' => 0,
            'image_proof' => $this->pngFile(),
        ])->assertSessionHasErrors('source_report_id');
    }
}
