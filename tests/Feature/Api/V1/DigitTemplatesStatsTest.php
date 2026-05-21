<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Domain\Models\DigitTemplateSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigitTemplatesStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('client_blobs');
    }

    public function test_stats_endpoint_is_public_and_returns_counts(): void
    {
        $token = AnonToken::create([
            'token_uuid' => strtolower((string) Str::uuid()),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        DigitTemplateSubmission::create([
            'anon_token_id' => $token->id,
            'char' => '0',
            'storage_path' => 'x.png',
            'phash' => '0000000000000000',
            'dim_w' => 20,
            'dim_h' => 32,
            'original_size_bytes' => 100,
            'submitted_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/templates/digits/stats');

        $response->assertStatus(200)
            ->assertJsonPath('total_templates', 1)
            ->assertJsonPath('contributors', 1)
            ->assertJsonPath('by_char.0', 1)
            ->assertJsonPath('consensus_ready', false);
    }

    public function test_version_filter_segments_stats(): void
    {
        $token = AnonToken::create([
            'token_uuid' => strtolower((string) Str::uuid()),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        DigitTemplateSubmission::create(['anon_token_id' => $token->id, 'char' => '0', 'storage_path' => 'a.png', 'phash' => '0', 'bot_version' => '0.5.0', 'dim_w' => 20, 'dim_h' => 32, 'original_size_bytes' => 1, 'submitted_at' => now()]);
        DigitTemplateSubmission::create(['anon_token_id' => $token->id, 'char' => '1', 'storage_path' => 'b.png', 'phash' => '1', 'bot_version' => '0.6.0', 'dim_w' => 20, 'dim_h' => 32, 'original_size_bytes' => 1, 'submitted_at' => now()]);

        $this->getJson('/api/v1/templates/digits/stats?version=0.5.0')
            ->assertStatus(200)
            ->assertJsonPath('total_templates', 1)
            ->assertJsonPath('version_filter', '0.5.0');
    }

    public function test_stats_empty_pool_returns_zero(): void
    {
        $this->getJson('/api/v1/templates/digits/stats')
            ->assertStatus(200)
            ->assertJsonPath('total_templates', 0)
            ->assertJsonPath('contributors', 0);
    }
}
