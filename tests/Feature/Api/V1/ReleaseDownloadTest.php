<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReleaseDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_returns_302_for_published_release(): void
    {
        Storage::fake('client_blobs');
        Storage::disk('client_blobs')->put('releases/0.5.0/AdenaLedgerStats.exe', 'fake exe bytes');

        Release::create([
            'version' => '0.5.0',
            'channel' => 'stable',
            'storage_path' => 'releases/0.5.0/AdenaLedgerStats.exe',
            'sha256' => str_repeat('a', 64),
            'size_bytes' => 14,
            'released_at' => now(),
            'published_at' => now(),
        ]);

        $response = $this->get('/api/v1/releases/0.5.0/download');
        $response->assertStatus(302);

        $this->assertSame(1, Release::first()->download_count);
    }

    public function test_download_404_for_missing_release(): void
    {
        $this->getJson('/api/v1/releases/9.9.9/download')->assertStatus(404);
    }

    public function test_serve_rejects_unsigned_request(): void
    {
        Storage::fake('client_blobs');
        Release::create([
            'version' => '0.5.0',
            'channel' => 'stable',
            'storage_path' => 'releases/0.5.0/AdenaLedgerStats.exe',
            'released_at' => now(),
            'published_at' => now(),
        ]);

        $this->get('/api/v1/releases/0.5.0/serve')->assertStatus(403);
    }
}
