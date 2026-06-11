<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\ClientApi\Domain\Models\GameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameSessionsTest extends TestCase
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

    private function clientHeaders(): array
    {
        return ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'char' => 'Antenita',
            'started_at' => '2026-06-11T10:00:00Z',
            'ended_at' => '2026-06-11T13:30:00Z',
            'xp' => 1500000,
            'sp' => 90000,
            'adena' => 250000,
            'mobs_killed' => 420,
            'deaths' => 1,
            'level_ups' => 2,
            'xp_per_hour' => 428571.4,
            'adena_per_hour' => 71428.5,
            'items_summary' => [
                ['name' => 'Animal Bone', 'count' => 130],
                ['name' => 'Varnish', 'count' => 88],
            ],
            'app_version' => '0.5.31-alpha',
        ], $overrides);
    }

    public function test_requires_client_key_and_anon_token(): void
    {
        $this->postJson('/api/v1/sessions', $this->payload())->assertStatus(401);
    }

    public function test_store_creates_session(): void
    {
        $response = $this->postJson('/api/v1/sessions', $this->payload(), $this->clientHeaders());

        $response->assertStatus(201)->assertJsonPath('status', 'accepted');

        $session = GameSession::first();
        $this->assertSame('Antenita', $session->char_name);
        $this->assertSame(1500000, $session->xp);
        $this->assertSame(428571, $session->xp_per_hour);
        $this->assertCount(2, $session->items_summary_json);
    }

    public function test_store_is_idempotent_for_outbox_retries(): void
    {
        $this->postJson('/api/v1/sessions', $this->payload(), $this->clientHeaders())
            ->assertStatus(201);

        // Retry del outbox: misma sesión (mismo install + char + started_at).
        $retry = $this->postJson('/api/v1/sessions', $this->payload(), $this->clientHeaders());
        $retry->assertStatus(200)->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, GameSession::count());
    }

    public function test_store_validates_dates_and_required_fields(): void
    {
        $this->postJson('/api/v1/sessions', $this->payload(['char' => null]), $this->clientHeaders())
            ->assertStatus(422);

        $this->postJson('/api/v1/sessions', $this->payload([
            'ended_at' => '2026-06-11T09:00:00Z', // antes de started_at
        ]), $this->clientHeaders())->assertStatus(422);
    }

    public function test_char_sessions_endpoint_is_public_and_paginated(): void
    {
        $this->postJson('/api/v1/sessions', $this->payload(), $this->clientHeaders())->assertStatus(201);
        $this->postJson('/api/v1/sessions', $this->payload([
            'started_at' => '2026-06-10T10:00:00Z',
            'ended_at' => '2026-06-10T11:00:00Z',
        ]), $this->clientHeaders())->assertStatus(201);
        $this->postJson('/api/v1/sessions', $this->payload([
            'char' => 'OtroChar',
        ]), $this->clientHeaders())->assertStatus(201);

        // Sin headers — público para la web.
        $response = $this->getJson('/api/v1/chars/Antenita/sessions');

        $response->assertStatus(200)
            ->assertJsonPath('char', 'Antenita')
            ->assertJsonPath('total', 2);

        // Orden: más reciente primero, con duración calculada.
        $sessions = $response->json('sessions');
        $this->assertSame(12600, $sessions[0]['duration_seconds']); // 3.5h
        $this->assertSame(3600, $sessions[1]['duration_seconds']);

        $paged = $this->getJson('/api/v1/chars/Antenita/sessions?limit=1&page=2');
        $paged->assertStatus(200)->assertJsonPath('total', 2);
        $this->assertCount(1, $paged->json('sessions'));
        $this->assertSame(3600, $paged->json('sessions.0.duration_seconds'));
    }

    public function test_char_sessions_returns_empty_for_unknown_char(): void
    {
        $this->getJson('/api/v1/chars/NadieConEsteNombre/sessions')
            ->assertStatus(200)
            ->assertJsonPath('total', 0);
    }
}
