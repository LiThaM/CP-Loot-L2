<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillTimingsTest extends TestCase
{
    use RefreshDatabase;

    private string $clientKey;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('client_blobs');
        $this->clientKey = 'lu4_test_'.Str::random(40);
        ClientApiKey::create(['key_hash' => ClientApiKey::hash($this->clientKey), 'label' => 'test', 'active' => true]);
    }

    private function clientHeaders(): array
    {
        return ['X-Client-Key' => $this->clientKey];
    }

    private function timings(): array
    {
        return [
            '1' => [
                '0' => ['duration' => null, 'hit_time' => null, 'mp_cost' => 42.0, 'reuse' => 3.0],
                '1' => ['duration' => null, 'hit_time' => null, 'mp_cost' => 43.0, 'reuse' => 3.0],
            ],
            '1204' => [
                '2' => ['duration' => 1200.0, 'hit_time' => 2.5, 'mp_cost' => 22.0, 'reuse' => 6.0],
            ],
        ];
    }

    private function actAsAdmin(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('test1234'),
            'role_id' => $role->id,
        ]);
        Sanctum::actingAs($admin, ['release:upload']);
    }

    public function test_get_requires_client_key(): void
    {
        $this->getJson('/api/v1/data/skill_timings')->assertStatus(401);
    }

    public function test_get_returns_404_when_not_uploaded(): void
    {
        $this->getJson('/api/v1/data/skill_timings', $this->clientHeaders())
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_uploaded');
    }

    public function test_upload_then_get_roundtrip_with_etag(): void
    {
        $this->actAsAdmin();
        // PRESERVE_ZERO_FRACTION: emula al scraper Python, que escribe 3.0.
        $upload = $this->call(
            'POST',
            '/api/v1/admin/data/skill_timings',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->timings(), JSON_PRESERVE_ZERO_FRACTION)
        );
        $upload->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('skills', 2);

        $response = $this->getJson('/api/v1/data/skill_timings', $this->clientHeaders());
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('ETag')
            ->assertHeader('Last-Modified');

        $this->assertSame(3.0, $response->json('1.0.reuse'));
        $this->assertSame(1200.0, $response->json('1204.2.duration'));

        // Segundo GET con el ETag → 304 sin body (lo que manda el cliente).
        $etag = $response->headers->get('ETag');
        $this->getJson('/api/v1/data/skill_timings', $this->clientHeaders() + ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_reupload_replaces_blob(): void
    {
        $this->actAsAdmin();
        $post = fn (array $body) => $this->call(
            'POST', '/api/v1/admin/data/skill_timings', [], [], [],
            ['CONTENT_TYPE' => 'application/json'], json_encode($body, JSON_PRESERVE_ZERO_FRACTION)
        );

        $post($this->timings())->assertStatus(200);
        $post(['99' => ['0' => ['reuse' => 1.0]]])->assertStatus(200)->assertJsonPath('skills', 1);

        $response = $this->getJson('/api/v1/data/skill_timings', $this->clientHeaders());
        $response->assertStatus(200);
        $this->assertSame(1.0, $response->json('99.0.reuse'));
        $this->assertNull($response->json('1'));
    }

    public function test_upload_rejects_invalid_payloads(): void
    {
        $this->actAsAdmin();
        $post = fn (string $raw) => $this->call(
            'POST', '/api/v1/admin/data/skill_timings', [], [], [],
            ['CONTENT_TYPE' => 'application/json'], $raw
        );

        $post('')->assertStatus(422)->assertJsonPath('error', 'empty_body');
        $post('not json')->assertStatus(422)->assertJsonPath('error', 'invalid_json');
        $post('{}')->assertStatus(422)->assertJsonPath('error', 'invalid_json');
        $post('{"1": "no es objeto"}')->assertStatus(422)->assertJsonPath('error', 'invalid_field');
    }

    public function test_upload_requires_sanctum(): void
    {
        $this->call(
            'POST', '/api/v1/admin/data/skill_timings', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode($this->timings())
        )->assertStatus(401);
    }
}
