<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makeRelease(string $version, array $overrides = []): Release
    {
        return Release::create(array_merge([
            'version' => $version,
            'channel' => 'stable',
            'release_notes_es' => 'Notas '.$version,
            'release_notes_en' => 'Notes '.$version,
            'released_at' => now(),
            'published_at' => now()->subMinute(),
        ], $overrides));
    }

    public function test_returns_only_published_releases(): void
    {
        $this->makeRelease('0.5.0', ['published_at' => now()->subDay()]);
        $this->makeRelease('0.5.1', ['published_at' => null]); // draft
        $this->makeRelease('0.5.2', ['published_at' => now()->subHour()]);

        $response = $this->getJson('/api/v1/changelog');

        $response->assertStatus(200)
            ->assertJsonPath('count', 2);

        $versions = collect($response->json('entries'))->pluck('version');
        $this->assertContains('0.5.0', $versions);
        $this->assertContains('0.5.2', $versions);
        $this->assertNotContains('0.5.1', $versions);
    }

    public function test_since_filter_returns_only_newer_versions(): void
    {
        $this->makeRelease('0.4.0', ['released_at' => now()->subDays(3), 'published_at' => now()->subDays(3)]);
        $this->makeRelease('0.5.0', ['released_at' => now()->subDays(2), 'published_at' => now()->subDays(2)]);
        $this->makeRelease('0.5.1', ['released_at' => now()->subDay(), 'published_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/changelog?since=0.5.0');

        $response->assertStatus(200)
            ->assertJsonPath('count', 1);

        $this->assertSame('0.5.1', $response->json('entries.0.version'));
    }

    public function test_excludes_future_published_releases(): void
    {
        $this->makeRelease('0.6.0', ['published_at' => now()->addWeek()]);

        $this->getJson('/api/v1/changelog')
            ->assertStatus(200)
            ->assertJsonPath('count', 0);
    }

    public function test_channel_filter(): void
    {
        $this->makeRelease('0.5.0', ['channel' => 'stable']);
        $this->makeRelease('0.6.0-beta', ['channel' => 'beta']);

        $this->getJson('/api/v1/changelog?channel=beta')
            ->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('entries.0.version', '0.6.0-beta');
    }
}
