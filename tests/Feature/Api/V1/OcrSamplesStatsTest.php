<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Domain\Models\OcrSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OcrSamplesStatsTest extends TestCase
{
    use RefreshDatabase;

    private function seedSample(AnonToken $token, array $overrides = []): OcrSample
    {
        return OcrSample::create(array_merge([
            'anon_token_id' => $token->id,
            'category' => 'chat',
            'storage_path' => 'fake/'.Str::random(8).'.png',
            'image_hash_sha256' => hash('sha256', Str::random(16)),
            'ground_truth' => 'Hello world',
            'status' => 'pending',
        ], $overrides));
    }

    private function makeAnon(?string $uuid = null): AnonToken
    {
        return AnonToken::create([
            'token_uuid' => strtolower($uuid ?? (string) Str::uuid()),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function test_stats_endpoint_is_public(): void
    {
        $t1 = $this->makeAnon();
        $t2 = $this->makeAnon();
        $this->seedSample($t1);
        $this->seedSample($t1, ['category' => 'chat_damage']);
        $this->seedSample($t2);

        $response = $this->getJson('/api/v1/ocr/samples/stats');

        $response->assertStatus(200)
            ->assertJsonPath('total_samples', 3)
            ->assertJsonPath('contributors', 2)
            ->assertJsonPath('by_category.chat', 2)
            ->assertJsonPath('by_category.chat_damage', 1)
            ->assertJsonPath('your_contribution', null);
    }

    public function test_your_contribution_filled_when_anon_token_header_present(): void
    {
        $uuid = '0192a4f3-1234-4567-89ab-cdef01234567';
        $token = $this->makeAnon($uuid);
        $this->seedSample($token);
        $this->seedSample($token);
        $this->seedSample($this->makeAnon());

        $this->getJson('/api/v1/ocr/samples/stats', ['X-Anon-Token' => $uuid])
            ->assertStatus(200)
            ->assertJsonPath('total_samples', 3)
            ->assertJsonPath('your_contribution', 2);
    }

    public function test_invalid_anon_token_header_is_ignored(): void
    {
        $this->getJson('/api/v1/ocr/samples/stats', ['X-Anon-Token' => 'not-a-uuid'])
            ->assertStatus(200)
            ->assertJsonPath('your_contribution', null);
    }

    public function test_labeled_count_reflects_status(): void
    {
        $t = $this->makeAnon();
        $this->seedSample($t, ['status' => 'labeled', 'reviewed_at' => now()]);
        $this->seedSample($t, ['status' => 'pending']);

        $this->getJson('/api/v1/ocr/samples/stats')
            ->assertStatus(200)
            ->assertJsonPath('labeled_samples', 1)
            ->assertJsonPath('total_samples', 2);
    }
}
