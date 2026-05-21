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

    public function test_response_includes_release_notes_for_current_locale(): void
    {
        Release::create([
            'version' => '0.5.4-alpha',
            'channel' => 'stable',
            'release_notes_es' => '### Añadido\n- Burst archive para AOE',
            'release_notes_en' => '### Added\n- Burst archive for AOE',
            'released_at' => now(),
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/version');
        $response->assertStatus(200);
        $body = $response->json();

        $this->assertArrayHasKey('release_notes', $body);
        $this->assertArrayHasKey('release_notes_es', $body);
        $this->assertArrayHasKey('release_notes_en', $body);
        $this->assertArrayHasKey('release_notes_url', $body);
        $this->assertSame('### Añadido\n- Burst archive para AOE', $body['release_notes_es']);
        $this->assertSame('### Added\n- Burst archive for AOE', $body['release_notes_en']);
    }

    public function test_release_notes_falls_back_across_columns_when_only_one_is_populated(): void
    {
        Release::create([
            'version' => '0.5.5-alpha',
            'channel' => 'stable',
            'release_notes_md' => null,
            'release_notes_es' => null,
            'release_notes_en' => '### English-only release notes',
            'released_at' => now(),
            'published_at' => now(),
        ]);

        // Default locale is `es` in tests; the cascading fallback should still
        // surface the English notes so the desktop modal isn't empty.
        $body = $this->getJson('/api/v1/version')->assertStatus(200)->json();
        $this->assertSame('### English-only release notes', $body['release_notes']);
    }

    public function test_release_notes_is_null_when_all_columns_are_null(): void
    {
        Release::create([
            'version' => '0.5.6-alpha',
            'channel' => 'stable',
            'release_notes_md' => null,
            'release_notes_es' => null,
            'release_notes_en' => null,
            'released_at' => now(),
            'published_at' => now(),
        ]);

        $body = $this->getJson('/api/v1/version')->assertStatus(200)->json();
        $this->assertNull($body['release_notes']);
        $this->assertNull($body['release_notes_url']);
    }
}
