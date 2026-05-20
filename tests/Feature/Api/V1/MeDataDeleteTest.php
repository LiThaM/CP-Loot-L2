<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\Telemetry\Domain\Models\DigitTemplateSubmission;
use App\Contexts\Telemetry\Domain\Models\ItemLu4UnknownReport;
use App\Contexts\Telemetry\Domain\Models\OcrSample;
use App\Contexts\Telemetry\Domain\Models\TelemetrySession;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeDataDeleteTest extends TestCase
{
    use RefreshDatabase;

    private string $clientKey;
    private string $anonUuid;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('client_blobs');
        $this->clientKey = 'lu4_test_'.Str::random(40);
        ClientApiKey::create(['key_hash' => ClientApiKey::hash($this->clientKey), 'label' => 'test', 'active' => true]);
        $this->anonUuid = (string) Str::uuid();
    }

    private function headers(): array
    {
        return ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid];
    }

    public function test_destroy_cascades_anon_token_data(): void
    {
        $token = AnonToken::create([
            'token_uuid' => strtolower($this->anonUuid),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $disk = Storage::disk('client_blobs');
        $disk->put('templates/digits/a.png', 'x');
        $disk->put('ocr/samples/b.png', 'y');

        DigitTemplateSubmission::create([
            'anon_token_id' => $token->id,
            'char' => '0',
            'storage_path' => 'templates/digits/a.png',
            'dim_w' => 20,
            'dim_h' => 32,
            'original_size_bytes' => 1,
            'submitted_at' => now(),
        ]);

        OcrSample::create([
            'anon_token_id' => $token->id,
            'category' => 'bar',
            'storage_path' => 'ocr/samples/b.png',
            'image_hash_sha256' => str_repeat('a', 64),
            'ground_truth' => '50/100',
        ]);

        TelemetrySession::create([
            'anon_token_id' => $token->id,
            'bot_version' => '0.5.0-alpha',
            'session_duration_seconds' => 600,
        ]);

        ItemLu4UnknownReport::create([
            'name' => 'Mystery item',
            'anon_token_id' => $token->id,
            'reported_at' => now(),
        ]);

        SupportTicket::create([
            'user_id' => null,
            'subject' => 'test',
            'message' => 'test',
            'status' => 'open',
            'type' => 'support',
            'source' => 'bot_app',
            'anon_token_id' => $token->id,
            'tracking_token' => Str::random(40),
            'bot_context_path' => null,
            'ticket_number' => 'TKT-TEST-001',
            'email' => 'tester@example.com',
        ]);

        $this->deleteJson('/api/v1/me/data', [], $this->headers())
            ->assertStatus(204);

        $this->assertSame(0, DigitTemplateSubmission::count());
        $this->assertSame(0, OcrSample::count());
        $this->assertSame(0, TelemetrySession::count());
        $this->assertSame(0, ItemLu4UnknownReport::count());
        $this->assertNull(AnonToken::find($token->id));
        // ticket persists but anonymized
        $ticket = SupportTicket::first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->anon_token_id);
        $this->assertNull($ticket->email);

        $disk->assertMissing('templates/digits/a.png');
        $disk->assertMissing('ocr/samples/b.png');
    }
}
