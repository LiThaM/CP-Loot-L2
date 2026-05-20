<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class BotTicketsTest extends TestCase
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

    private function makeBotContextZip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bctx_').'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        return new UploadedFile($path, 'bot_context.zip', 'application/zip', null, true);
    }

    public function test_creates_ticket_with_bot_context(): void
    {
        $zip = $this->makeBotContextZip([
            'log_tail.txt' => "line 1\nline 2",
            'settings.json' => '{"alpha":0.9}',
            'session_stats.json' => '{"xp":1000}',
        ]);

        $response = $this->postJson('/api/v1/tickets', [
            'category' => 'ocr_error',
            'subject' => 'OCR misreads HP bar',
            'message' => 'It reads 12345 as 32345 sometimes.',
            'email' => 'tester@example.com',
            'bot_context' => $zip,
        ], $this->headers());

        $response->assertStatus(201)
            ->assertJsonPath('status', 'created');

        $this->assertSame(1, SupportTicket::count());
        $ticket = SupportTicket::first();
        $this->assertSame('bot_app', $ticket->source);
        $this->assertNotNull($ticket->bot_context_path);
        $this->assertSame('ocr_error', data_get($ticket->metadata, 'category'));
        Storage::disk('client_blobs')->assertExists($ticket->bot_context_path);
    }

    public function test_creates_ticket_without_bot_context(): void
    {
        $response = $this->postJson('/api/v1/tickets', [
            'category' => 'feature_request',
            'subject' => 'Add CSV export',
            'message' => 'Would be great to export session stats as CSV.',
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertSame(1, SupportTicket::count());
        $this->assertNull(SupportTicket::first()->bot_context_path);
    }

    public function test_bot_context_with_unauthorized_entry_is_rejected(): void
    {
        $zip = $this->makeBotContextZip([
            'log_tail.txt' => 'ok',
            'evil.exe' => 'malware',
        ]);

        $response = $this->postJson('/api/v1/tickets', [
            'category' => 'crash',
            'subject' => 'Bot crashes on launch',
            'message' => 'Crashes immediately.',
            'bot_context' => $zip,
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertNotNull(SupportTicket::first()->bot_context_path);
    }

    public function test_tracking_token_returns_ticket_status(): void
    {
        $this->postJson('/api/v1/tickets', [
            'category' => 'other',
            'subject' => 'hello',
            'message' => 'hi there',
        ], $this->headers())->assertStatus(201);

        $ticket = SupportTicket::first();
        $this->assertNotNull($ticket->tracking_token);

        $response = $this->getJson('/api/v1/tickets/'.$ticket->tracking_token);

        $response->assertStatus(200)
            ->assertJsonPath('ticket_number', $ticket->ticket_number)
            ->assertJsonPath('subject', 'hello')
            ->assertJsonPath('status', 'open');
    }

    public function test_tracking_invalid_token_returns_404(): void
    {
        $this->getJson('/api/v1/tickets/'.str_repeat('a', 40))
            ->assertStatus(404);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->postJson('/api/v1/tickets', [
            'category' => 'nuke_world',
            'subject' => 'x',
            'message' => 'y',
        ], $this->headers())->assertStatus(422)
          ->assertJsonValidationErrors('category');
    }
}
