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
use App\Contexts\System\Domain\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseSellAutoTest extends TestCase
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
        Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);

        $this->leader = User::create([
            'name' => 'Leader', 'email' => 'leader@auto.local', 'password' => bcrypt('x'),
            'role_id' => $leaderRole->id, 'membership_status' => 'approved',
        ]);
        $this->cp = ConstParty::create([
            'leader_id' => $this->leader->id, 'name' => 'AutoCP', 'chronicle' => 'IL', 'is_active' => true,
        ]);
        $this->leader->update(['cp_id' => $this->cp->id]);

        $this->item = Item::create(['name' => 'EAB', 'category' => 'EtcItem']);
        $this->adena = Item::create(['name' => 'Adena', 'category' => 'Material']);
    }

    private function member(string $name): User
    {
        $role = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        return User::create([
            'name' => $name, 'email' => strtolower($name).'@a.l', 'password' => bcrypt('x'),
            'role_id' => $role->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved',
        ]);
    }

    private function farm(int $itemAmount, array $attendees, int $cpSharePct = 0): LootReport
    {
        $report = LootReport::create([
            'cp_id' => $this->cp->id,
            'requested_by_id' => $this->leader->id,
            'event_type' => 'FARM',
            'status' => 'confirmed',
            'cp_share_pct' => $cpSharePct,
        ]);
        LootEntry::create(['loot_report_id' => $report->id, 'item_id' => $this->item->id, 'amount' => $itemAmount]);
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

    private function png(): UploadedFile
    {
        $img = imagecreatetruecolor(8, 8);
        $path = tempnam(sys_get_temp_dir(), 'auto_').'.png';
        imagepng($img, $path);
        imagedestroy($img);
        return new UploadedFile($path, 'proof.png', 'image/png', null, true);
    }

    public function test_auto_spread_creates_one_sell_per_source(): void
    {
        $m1 = $this->member('Alice');
        $m2 = $this->member('Bob');
        $m3 = $this->member('Carol');

        // Three farms: 1 EAB each (1+1+3 split? actually let me follow the user example).
        $farmA = $this->farm(1, [['user_id' => $m1->id]], cpSharePct: 0);
        $farmB = $this->farm(1, [['user_id' => $m2->id]], cpSharePct: 50);
        $farmC = $this->farm(3, [['user_id' => $m3->id]], cpSharePct: 20);

        $response = $this->actingAs($this->leader)->post(route('warehouse.sell-auto'), [
            'item_id' => $this->item->id,
            'total_amount' => 5,
            'unit_price' => 1_000_000,
            'allocations' => [
                ['source_report_id' => $farmA->id, 'amount' => 1],
                ['source_report_id' => $farmB->id, 'amount' => 1],
                ['source_report_id' => $farmC->id, 'amount' => 3],
            ],
            'image_proof' => $this->png(),
        ]);
        $response->assertRedirect();

        $sells = LootReport::where('event_type', 'SELL')->orderBy('id')->get();
        $this->assertCount(3, $sells);

        // Farm A: cp=0%, 1*1_000_000 = 1_000_000 → m1 receives 1_000_000
        $this->assertDatabaseHas('points_logs', ['user_id' => $m1->id, 'adena' => 1_000_000]);
        // Farm B: cp=50%, 1*1_000_000 = 1_000_000 → cp=500k, m2=500k
        $this->assertDatabaseHas('points_logs', ['user_id' => $m2->id, 'adena' => 500_000]);
        // Farm C: cp=20%, 3*1_000_000 = 3_000_000 → cp=600k, m3=2_400_000
        $this->assertDatabaseHas('points_logs', ['user_id' => $m3->id, 'adena' => 2_400_000]);
    }

    public function test_shortage_returns_422(): void
    {
        $m1 = $this->member('Alice');
        $farmA = $this->farm(2, [['user_id' => $m1->id]]);

        $this->actingAs($this->leader)->from('/party')->post(route('warehouse.sell-auto'), [
            'item_id' => $this->item->id,
            'total_amount' => 5,
            'unit_price' => 1_000_000,
            'allocations' => [
                ['source_report_id' => $farmA->id, 'amount' => 5],
            ],
            'image_proof' => $this->png(),
        ])->assertSessionHasErrors('allocations');
    }

    public function test_single_source_backward_compat(): void
    {
        $m1 = $this->member('Alice');
        $farmA = $this->farm(5, [['user_id' => $m1->id]], cpSharePct: 20);

        $this->actingAs($this->leader)->post(route('warehouse.sell-auto'), [
            'item_id' => $this->item->id,
            'total_amount' => 5,
            'unit_price' => 1_000_000,
            'allocations' => [
                ['source_report_id' => $farmA->id, 'amount' => 5],
            ],
            'image_proof' => $this->png(),
        ])->assertRedirect();

        $this->assertSame(1, LootReport::where('event_type', 'SELL')->count());
        // 5_000_000 * 20% = 1_000_000 to cp; 4_000_000 to m1.
        $this->assertDatabaseHas('points_logs', ['user_id' => $m1->id, 'adena' => 4_000_000]);
    }

    public function test_farm_without_attendees_under_100_pct_is_rejected(): void
    {
        // Farm con 0 attendees y cp_share_pct=20 → no se puede repartir.
        $farmA = $this->farm(5, [], cpSharePct: 20);

        $this->actingAs($this->leader)->from('/party')->post(route('warehouse.sell-auto'), [
            'item_id' => $this->item->id,
            'total_amount' => 5,
            'unit_price' => 1,
            'allocations' => [
                ['source_report_id' => $farmA->id, 'amount' => 5],
            ],
            'image_proof' => $this->png(),
        ])->assertSessionHasErrors('allocations');
    }

    public function test_batch_id_is_shared_across_all_reports(): void
    {
        $m1 = $this->member('Alice');
        $m2 = $this->member('Bob');
        $farmA = $this->farm(2, [['user_id' => $m1->id]]);
        $farmB = $this->farm(3, [['user_id' => $m2->id]]);

        $this->actingAs($this->leader)->post(route('warehouse.sell-auto'), [
            'item_id' => $this->item->id,
            'total_amount' => 5,
            'unit_price' => 100,
            'allocations' => [
                ['source_report_id' => $farmA->id, 'amount' => 2],
                ['source_report_id' => $farmB->id, 'amount' => 3],
            ],
            'image_proof' => $this->png(),
        ])->assertRedirect();

        $audits = AuditLog::where('action', 'WAREHOUSE_SELL')->get();
        $this->assertCount(2, $audits);
        $batchIds = $audits->pluck('new_values')->map(fn ($nv) => $nv['auto_allocation_batch_id'] ?? null);
        $this->assertCount(1, $batchIds->unique());
        $this->assertNotNull($batchIds->first());
    }

    public function test_allocation_sum_must_equal_total_amount(): void
    {
        $m1 = $this->member('Alice');
        $farmA = $this->farm(5, [['user_id' => $m1->id]]);

        $this->actingAs($this->leader)->from('/party')->post(route('warehouse.sell-auto'), [
            'item_id' => $this->item->id,
            'total_amount' => 5,
            'unit_price' => 100,
            'allocations' => [
                ['source_report_id' => $farmA->id, 'amount' => 3],
            ],
            'image_proof' => $this->png(),
        ])->assertSessionHasErrors('allocations');
    }
}
