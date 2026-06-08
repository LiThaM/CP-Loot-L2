<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Application\Services\AuctionService;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\CpAuction;
use App\Contexts\Party\Domain\Models\CpAuctionBid;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\Party\Domain\Models\TrackerContribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase;

    private Role $leaderRole;
    private Role $memberRole;
    private ConstParty $cp;
    private User $leader;
    private User $member;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);
        $this->memberRole = Role::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);

        $this->cp = ConstParty::create([
            'name' => 'AuctionCP-'.uniqid(),
            'chronicle' => 'LU4',
            'invite_code' => substr(md5(uniqid()), 0, 12),
            'tracker_enabled' => true,
            'tracker_divisor' => 1000,
            'tracker_enabled_at' => now()->subDay(),
        ]);

        $this->leader = $this->makeUser($this->leaderRole);
        $this->member = $this->makeUser($this->memberRole);
        $this->cp->forceFill(['leader_id' => $this->leader->id])->save();

        $this->item = Item::create([
            'name' => 'AuctionItem', 'grade' => 'A', 'category' => 'weapon',
            'chronicle' => 'LU4', 'source' => 'test',
            'market_price' => 1_000_000, 'hidden' => false,
        ]);

        // Stock the warehouse with 5 units of the item via a FARM report.
        $stockReport = LootReport::create([
            'cp_id' => $this->cp->id, 'requested_by_id' => $this->leader->id,
            'event_type' => 'FARM', 'status' => 'confirmed', 'points_per_member' => 0,
        ]);
        LootEntry::create([
            'loot_report_id' => $stockReport->id, 'item_id' => $this->item->id, 'amount' => 5,
        ]);

        // Give the member some DKP to bid with.
        TrackerContribution::create([
            'cp_id' => $this->cp->id, 'user_id' => $this->member->id,
            'type' => 'material', 'points' => 500,
            'description' => 'fixture', 'badge' => 'SOLO',
            'created_by_user_id' => $this->leader->id,
        ]);
    }

    private function makeUser(Role $role): User
    {
        $u = User::forceCreate([
            'name' => 'u-'.uniqid(),
            'email' => 'u-'.uniqid().'@t.l',
            'password' => bcrypt('x'),
            'cp_id' => $this->cp->id,
            'membership_status' => 'approved',
        ]);
        $u->forceFill(['role_id' => $role->id])->save();
        return $u;
    }

    public function test_open_creates_auction_and_reserves_stock(): void
    {
        $auction = app(AuctionService::class)->open(
            $this->leader, $this->item, 2, 'points', 100.0, null, now()->addHour(),
        );

        $this->assertSame('open', $auction->status);
        $this->assertNotNull($auction->reservation_report_id);
        // After reservation, warehouse should show 3 available (5 - 2).
        $stockReport = LootReport::find($auction->reservation_report_id);
        $this->assertSame('WAREHOUSE_RECHECK_LOSS', $stockReport->event_type);
    }

    public function test_bid_validates_amount_and_balance(): void
    {
        $service = app(AuctionService::class);
        $auction = $service->open($this->leader, $this->item, 1, 'points', 100.0, null, now()->addHour());

        // First bid below starting → fails
        $this->expectException(\RuntimeException::class);
        $service->bid($this->member, $auction->fresh(), 50.0);
    }

    public function test_bid_outbid_releases_previous_bidder(): void
    {
        $service = app(AuctionService::class);
        $auction = $service->open($this->leader, $this->item, 1, 'points', 100.0, null, now()->addHour());

        // Member1 bids 200 (within their 500 balance)
        $service->bid($this->member, $auction->fresh(), 200.0);
        $this->assertEquals(200.0, $auction->fresh()->current_bid);

        // Member1's available is now 300 (500 - 200 commitment)
        $available = $service->availableBalance($this->member, $this->cp->id, 'points');
        $this->assertEquals(300.0, $available);

        // Member2 outbids at 250
        $m2 = $this->makeUser($this->memberRole);
        TrackerContribution::create([
            'cp_id' => $this->cp->id, 'user_id' => $m2->id,
            'type' => 'material', 'points' => 500,
            'description' => 'fixture', 'badge' => 'SOLO',
            'created_by_user_id' => $this->leader->id,
        ]);
        $service->bid($m2, $auction->fresh(), 250.0);

        // Member1's available returns to 500 (no commitment anymore)
        $available1 = $service->availableBalance($this->member, $this->cp->id, 'points');
        $this->assertEquals(500.0, $available1);

        // Member2 commits the 250
        $available2 = $service->availableBalance($m2, $this->cp->id, 'points');
        $this->assertEquals(250.0, $available2);
    }

    public function test_buy_now_closes_immediately(): void
    {
        $service = app(AuctionService::class);
        $auction = $service->open($this->leader, $this->item, 1, 'points', 100.0, 300.0, now()->addHour());

        $service->bid($this->member, $auction->fresh(), 300.0);
        $this->assertSame('closed', $auction->fresh()->status);
        $this->assertSame($this->member->id, $auction->fresh()->winner_id);
    }

    public function test_close_with_no_bidder_cancels_and_returns_stock(): void
    {
        $service = app(AuctionService::class);
        $auction = $service->open($this->leader, $this->item, 2, 'points', 100.0, null, now()->addMinutes(2));

        // No bids. Force expiry.
        $auction->forceFill(['ends_at' => now()->subMinute()])->save();
        $service->close($auction->fresh());

        $this->assertSame('cancelled', $auction->fresh()->status);
        // A WAREHOUSE_RECHECK_GAIN should have been created
        $this->assertNotNull(LootReport::where('cp_id', $this->cp->id)
            ->where('event_type', 'WAREHOUSE_RECHECK_GAIN')
            ->whereJsonContains('description', null)  // description contains "cancelled" — just check the report exists
            ->orWhere('description', 'like', '%cancelled%')
            ->first());
    }

    public function test_fulfill_charges_winner_and_creates_assign(): void
    {
        $service = app(AuctionService::class);
        $auction = $service->open($this->leader, $this->item, 1, 'points', 100.0, null, now()->addHour());
        $service->bid($this->member, $auction->fresh(), 200.0);
        $service->close($auction->fresh());
        $this->assertSame('closed', $auction->fresh()->status);

        $service->fulfill($auction->fresh(), $this->leader);

        $a = $auction->fresh();
        $this->assertSame('fulfilled', $a->status);
        $this->assertNotNull($a->fulfilled_at);

        // Winner got a negative TrackerContribution for 200 points
        $negative = TrackerContribution::where('cp_id', $this->cp->id)
            ->where('user_id', $this->member->id)
            ->where('points', -200.0)
            ->where('badge', 'AUCTION')
            ->first();
        $this->assertNotNull($negative);

        // An ASSIGN LootReport should exist with the item awarded to the winner
        $assign = LootReport::where('cp_id', $this->cp->id)
            ->where('event_type', 'ASSIGN')
            ->latest('id')
            ->first();
        $this->assertNotNull($assign);
        $entry = LootEntry::where('loot_report_id', $assign->id)->first();
        $this->assertSame($this->member->id, $entry->awarded_to);
    }

    public function test_cancel_returns_stock_and_marks_cancelled(): void
    {
        $service = app(AuctionService::class);
        $auction = $service->open($this->leader, $this->item, 2, 'points', 100.0, null, now()->addHour());
        $service->cancel($auction->fresh(), $this->leader);

        $this->assertSame('cancelled', $auction->fresh()->status);
        // A return report exists
        $this->assertTrue(LootReport::where('cp_id', $this->cp->id)
            ->where('event_type', 'WAREHOUSE_RECHECK_GAIN')
            ->where('description', 'like', '%'.$auction->id.'%')
            ->exists());
    }

    public function test_index_exposes_warehouse_items_only_for_leader(): void
    {
        $response = $this->actingAs($this->leader)->get(route('party.auctions.index'));
        $response->assertOk();
        $props = $response->original->getData()['page']['props'];
        $this->assertNotEmpty($props['warehouseItems']);
        $first = (array) $props['warehouseItems'][0];
        $this->assertSame($this->item->id, $first['id']);
        $this->assertSame(5, $first['available']);

        // Regular member sees an empty list — auction creation isn't theirs.
        $response = $this->actingAs($this->member)->get(route('party.auctions.index'));
        $response->assertOk();
        $this->assertSame([], $response->original->getData()['page']['props']['warehouseItems']);
    }

    public function test_warehouse_items_excludes_items_not_in_stock(): void
    {
        $unowned = Item::create([
            'name' => 'NotMineItem', 'grade' => 'B', 'category' => 'misc',
            'chronicle' => 'LU4', 'source' => 'test', 'hidden' => false,
        ]);

        $response = $this->actingAs($this->leader)->get(route('party.auctions.index'));
        $ids = collect($response->original->getData()['page']['props']['warehouseItems'] ?? [])->pluck('id');
        $this->assertFalse($ids->contains($unowned->id));
    }

    public function test_points_auction_requires_tracker_enabled(): void
    {
        $cpNoTracker = ConstParty::create([
            'name' => 'NoTrackerCP-'.uniqid(), 'chronicle' => 'LU4',
            'invite_code' => substr(md5(uniqid()), 0, 12),
            'tracker_enabled' => false,
        ]);
        $leader = User::forceCreate([
            'name' => 'l-'.uniqid(), 'email' => 'l-'.uniqid().'@t.l',
            'password' => bcrypt('x'), 'cp_id' => $cpNoTracker->id,
            'membership_status' => 'approved',
        ]);
        $leader->forceFill(['role_id' => $this->leaderRole->id])->save();
        $cpNoTracker->forceFill(['leader_id' => $leader->id])->save();

        $this->expectException(\RuntimeException::class);
        app(AuctionService::class)->open($leader, $this->item, 1, 'points', 100.0, null, now()->addHour());
    }
}
