<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_returns_most_recent_published_stable_release(): void
    {
        Release::create([
            'version' => '0.5.0-alpha',
            'channel' => 'stable',
            'released_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);

        Release::create([
            'version' => '0.5.1-alpha',
            'channel' => 'stable',
            'critical_update' => false,
            'min_supported_version' => '0.4.0-alpha',
            'released_at' => now(),
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/version');

        $response->assertStatus(200)
            ->assertJsonPath('latest_version', '0.5.1-alpha')
            ->assertJsonPath('channel', 'stable')
            ->assertJsonPath('critical_update', false)
            ->assertJsonPath('min_supported_version', '0.4.0-alpha');
    }

    public function test_latest_filters_by_channel_query_param(): void
    {
        Release::create([
            'version' => '0.5.0-alpha',
            'channel' => 'stable',
            'released_at' => now(),
            'published_at' => now(),
        ]);

        Release::create([
            'version' => '0.6.0-beta',
            'channel' => 'beta',
            'released_at' => now(),
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/version?channel=beta')
            ->assertJsonPath('latest_version', '0.6.0-beta')
            ->assertJsonPath('channel', 'beta');
    }

    public function test_latest_ignores_unpublished_drafts(): void
    {
        Release::create([
            'version' => '0.5.0-alpha',
            'channel' => 'stable',
            'released_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);

        Release::create([
            'version' => '0.6.0-alpha',
            'channel' => 'stable',
            'released_at' => now(),
            'published_at' => null,
        ]);

        $this->getJson('/api/v1/version')
            ->assertJsonPath('latest_version', '0.5.0-alpha');
    }

    public function test_returns_404_when_no_releases_published(): void
    {
        $this->getJson('/api/v1/version')->assertStatus(404);
    }
}
