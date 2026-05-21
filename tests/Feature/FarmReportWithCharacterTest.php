<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Character;
use App\Contexts\Identity\Domain\Models\L2Class;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FarmReportWithCharacterTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;
    private User $member;
    private ConstParty $cp;
    private Item $item;
    private L2Class $bishop;

    private static int $next = 200000;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'Leader']);
        $memberRole = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);

        $this->leader = User::create(['name' => 'Leader', 'email' => 'l@t.l', 'password' => bcrypt('x'), 'role_id' => $leaderRole->id, 'membership_status' => 'approved']);
        $this->cp = ConstParty::create(['leader_id' => $this->leader->id, 'name' => 'CP', 'chronicle' => 'IL', 'is_active' => true]);
        $this->leader->update(['cp_id' => $this->cp->id]);

        $this->member = User::create(['name' => 'Member', 'email' => 'm@t.l', 'password' => bcrypt('x'), 'role_id' => $memberRole->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);

        $this->item = Item::create(['name' => 'TestLoot', 'chronicle' => 'IL', 'external_id' => self::$next++]);
        $this->seed(\Database\Seeders\L2ClassSeeder::class);
        $this->bishop = L2Class::where('code', 'human_bishop')->firstOrFail();
    }

    public function test_loot_store_persists_character_id_when_provided(): void
    {
        $char = Character::create(['user_id' => $this->member->id, 'name' => 'Alt', 'l2_class_id' => $this->bishop->id]);

        $this->actingAs($this->leader)
            ->post(route('loot.report.store'), [
                'event_type' => 'FARM',
                'items' => [['item_id' => $this->item->id, 'amount' => 1]],
                'attendees' => [
                    ['user_id' => $this->member->id, 'character_id' => $char->id],
                ],
            ])->assertRedirect();

        $att = LootReportAttendee::firstOrFail();
        $this->assertSame($this->member->id, $att->user_id);
        $this->assertSame($char->id, $att->character_id);
    }

    public function test_loot_store_drops_character_id_when_it_belongs_to_another_user(): void
    {
        $foreign = User::create(['name' => 'Foreign', 'email' => 'f@t.l', 'password' => bcrypt('x'), 'role_id' => Role::firstOrCreate(['name' => 'cp_member'])->id, 'cp_id' => $this->cp->id, 'membership_status' => 'approved']);
        $foreignChar = Character::create(['user_id' => $foreign->id, 'name' => 'NotYours', 'l2_class_id' => $this->bishop->id]);

        $this->actingAs($this->leader)
            ->post(route('loot.report.store'), [
                'event_type' => 'FARM',
                'items' => [['item_id' => $this->item->id, 'amount' => 1]],
                'attendees' => [
                    ['user_id' => $this->member->id, 'character_id' => $foreignChar->id],
                ],
            ])->assertRedirect();

        $att = LootReportAttendee::firstOrFail();
        $this->assertSame($this->member->id, $att->user_id);
        $this->assertNull($att->character_id);
    }

    public function test_loot_store_without_character_id_defaults_to_null_main(): void
    {
        $this->actingAs($this->leader)
            ->post(route('loot.report.store'), [
                'event_type' => 'FARM',
                'items' => [['item_id' => $this->item->id, 'amount' => 1]],
                'attendees' => [['user_id' => $this->member->id]],
            ])->assertRedirect();

        $att = LootReportAttendee::firstOrFail();
        $this->assertNull($att->character_id);
    }
}
