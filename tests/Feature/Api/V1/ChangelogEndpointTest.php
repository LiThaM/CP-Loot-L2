<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\System\Domain\Models\ChangelogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_published_entries_for_audience(): void
    {
        ChangelogEntry::create([
            'type' => 'feature',
            'version' => '0.5.0',
            'audience' => 'bot',
            'title_es' => 'Nueva feature',
            'title_en' => 'New feature',
            'published_at' => now()->subDay(),
        ]);

        ChangelogEntry::create([
            'type' => 'feature',
            'version' => '0.5.1',
            'audience' => 'web',
            'title_es' => 'Solo web',
            'title_en' => 'Web only',
            'published_at' => now()->subHour(),
        ]);

        ChangelogEntry::create([
            'type' => 'fix',
            'version' => '0.5.2',
            'audience' => 'both',
            'title_es' => 'Fix general',
            'title_en' => 'General fix',
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/changelog?audience=bot');

        $response->assertStatus(200)
            ->assertJsonPath('count', 2)
            ->assertJsonPath('audience', 'bot');

        $titles = collect($response->json('entries'))->pluck('title_en');
        $this->assertContains('General fix', $titles);
        $this->assertContains('New feature', $titles);
        $this->assertNotContains('Web only', $titles);
    }

    public function test_since_filter_returns_only_newer_versions(): void
    {
        ChangelogEntry::create(['type' => 'feature', 'version' => '0.4.0', 'audience' => 'both', 'title_es' => 'a', 'title_en' => 'a', 'published_at' => now()->subDay()]);
        ChangelogEntry::create(['type' => 'feature', 'version' => '0.5.0', 'audience' => 'both', 'title_es' => 'b', 'title_en' => 'b', 'published_at' => now()->subHour()]);
        ChangelogEntry::create(['type' => 'feature', 'version' => '0.5.1', 'audience' => 'both', 'title_es' => 'c', 'title_en' => 'c', 'published_at' => now()]);

        $response = $this->getJson('/api/v1/changelog?audience=bot&since=0.5.0');

        $response->assertStatus(200)
            ->assertJsonPath('count', 1);

        $this->assertSame('0.5.1', $response->json('entries.0.version'));
    }

    public function test_excludes_future_published_entries(): void
    {
        ChangelogEntry::create([
            'type' => 'feature',
            'version' => '0.6.0',
            'audience' => 'both',
            'title_es' => 'futuro',
            'title_en' => 'future',
            'published_at' => now()->addWeek(),
        ]);

        $this->getJson('/api/v1/changelog?audience=bot')
            ->assertStatus(200)
            ->assertJsonPath('count', 0);
    }
}
