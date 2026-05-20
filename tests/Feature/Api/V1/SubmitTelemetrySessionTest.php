<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\Telemetry\Domain\Models\TelemetrySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmitTelemetrySessionTest extends TestCase
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

    private function headers(): array
    {
        return ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid];
    }

    public function test_happy_path_creates_session(): void
    {
        $payload = [
            'bot_version' => '0.5.0-alpha',
            'os_version' => 'Windows-11-26100',
            'python_version' => '3.12.10',
            'session_duration_seconds' => 3600,
            'char_class' => 'Bishop',
            'char_level' => 55,
            'xp_per_hour' => 250000,
            'adena_per_hour' => 50000,
            'ss_per_hour' => 1200,
            'deaths' => 0,
            'level_ups' => 1,
            'top_items' => ['Iron Ore', 'Stem', 'Varnish'],
            'ocr_engine' => 'rapidocr',
            'ocr_avg_ms' => 12.4,
            'ocr_p95_ms' => 24.0,
            'ocr_errors' => 3,
            'ocr_gpu_used' => false,
        ];

        $response = $this->postJson('/api/v1/telemetry/session', $payload, $this->headers());

        $response->assertStatus(201)->assertJsonPath('status', 'accepted');
        $this->assertSame(1, TelemetrySession::count());

        $row = TelemetrySession::first();
        $this->assertSame('Bishop', $row->char_class);
        $this->assertSame(['Iron Ore', 'Stem', 'Varnish'], $row->top_items_json);
        $this->assertSame('rapidocr', $row->ocr_engine);
    }

    public function test_char_name_in_payload_is_rejected(): void
    {
        $this->postJson('/api/v1/telemetry/session', [
            'bot_version' => '0.5.0-alpha',
            'session_duration_seconds' => 60,
            'char_name' => 'Antenita',
        ], $this->headers())->assertStatus(422)
          ->assertJsonValidationErrors('char_name');
    }

    public function test_invalid_duration_is_rejected(): void
    {
        $this->postJson('/api/v1/telemetry/session', [
            'bot_version' => '0.5.0-alpha',
            'session_duration_seconds' => -1,
        ], $this->headers())->assertStatus(422)
          ->assertJsonValidationErrors('session_duration_seconds');
    }

    public function test_geoip_returns_null_when_provider_not_configured(): void
    {
        $this->postJson('/api/v1/telemetry/session', [
            'bot_version' => '0.5.0-alpha',
            'session_duration_seconds' => 60,
        ], $this->headers())->assertStatus(201)
          ->assertJsonPath('country_code', null);
    }
}
