<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Telemetry\Domain\Models\ItemLu4UnknownReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemsLu4Test extends TestCase
{
    use RefreshDatabase;

    private string $clientKey;
    private string $anonUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientKey = 'lu4_test_'.Str::random(40);
        ClientApiKey::create(['key_hash' => ClientApiKey::hash($this->clientKey), 'label' => 'test', 'active' => true]);
        $this->anonUuid = (string) Str::uuid();
    }

    private function clientHeaders(bool $withAnon = false): array
    {
        return $withAnon
            ? ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid]
            : ['X-Client-Key' => $this->clientKey];
    }

    public function test_index_returns_only_lu4_items(): void
    {
        Item::create(['name' => 'Lu4 Bow', 'chronicle' => 'LU4', 'source' => 'lu4_custom', 'category' => 'Weapon']);
        Item::create(['name' => 'C5 Hat', 'chronicle' => 'C5', 'source' => 'elmore_scrap', 'category' => 'Armor']);
        Item::create(['name' => 'Custom Cape', 'chronicle' => 'C4', 'source' => 'lu4_custom', 'category' => 'Armor']);

        $response = $this->getJson('/api/v1/items/lu4', $this->clientHeaders());

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertCount(2, $body['data']);
        $names = array_column($body['data'], 'name');
        $this->assertContains('Lu4 Bow', $names);
        $this->assertContains('Custom Cape', $names);
        $this->assertNotContains('C5 Hat', $names);
    }

    public function test_index_returns_304_when_etag_matches(): void
    {
        Item::create(['name' => 'Lu4 Bow', 'chronicle' => 'LU4', 'source' => 'lu4_custom', 'category' => 'Weapon']);

        $first = $this->getJson('/api/v1/items/lu4', $this->clientHeaders());
        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);

        $second = $this->getJson('/api/v1/items/lu4', array_merge($this->clientHeaders(), [
            'If-None-Match' => $etag,
        ]));
        $second->assertStatus(304);
    }

    public function test_report_unknown_creates_report(): void
    {
        $response = $this->postJson('/api/v1/items/lu4/report-unknown', [
            'name' => 'Mysterious Lu4 Coin',
            'ocr_context' => 'Antenita has obtained Mysterious Lu4 Coin.',
            'count_seen' => 3,
        ], $this->clientHeaders(withAnon: true));

        $response->assertStatus(201)->assertJsonPath('status', 'accepted');
        $this->assertSame(1, ItemLu4UnknownReport::count());
    }

    public function test_report_unknown_increments_existing_pending(): void
    {
        $this->postJson('/api/v1/items/lu4/report-unknown', [
            'name' => 'Mysterious Lu4 Coin',
            'count_seen' => 2,
        ], $this->clientHeaders(withAnon: true))->assertStatus(201);

        $this->postJson('/api/v1/items/lu4/report-unknown', [
            'name' => 'Mysterious Lu4 Coin',
            'count_seen' => 5,
        ], $this->clientHeaders(withAnon: true))->assertStatus(200)
          ->assertJsonPath('status', 'updated')
          ->assertJsonPath('count_seen', 7);

        $this->assertSame(1, ItemLu4UnknownReport::count());
    }
}
