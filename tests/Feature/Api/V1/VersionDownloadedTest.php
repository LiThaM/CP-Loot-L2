<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\ClientApi\Domain\Models\VersionDownload;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VersionDownloadedTest extends TestCase
{
    use RefreshDatabase;

    private string $clientKey;
    private string $anonUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientKey = 'lu4_test_'.Str::random(40);
        ClientApiKey::create(['key_hash' => ClientApiKey::hash($this->clientKey), 'label' => 'test', 'active' => true]);
        $this->anonUuid = (string) Str::uuid();
    }

    private function clientHeaders(?string $anonUuid = null): array
    {
        return ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $anonUuid ?? $this->anonUuid];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'from_version' => '0.5.31-alpha',
            'to_version' => '0.5.32-alpha',
        ], $overrides);
    }

    public function test_requires_client_key_and_anon_token(): void
    {
        $this->postJson('/api/v1/version/downloaded', $this->payload())->assertStatus(401);
    }

    public function test_store_records_download(): void
    {
        $response = $this->postJson('/api/v1/version/downloaded', $this->payload(), $this->clientHeaders());

        $response->assertStatus(201)->assertJsonPath('status', 'accepted');

        $download = VersionDownload::first();
        $this->assertSame('0.5.31-alpha', $download->from_version);
        $this->assertSame('0.5.32-alpha', $download->to_version);
        $this->assertNotNull($download->anon_token_id);
    }

    public function test_retry_same_install_same_version_is_idempotent(): void
    {
        $this->postJson('/api/v1/version/downloaded', $this->payload(), $this->clientHeaders())
            ->assertStatus(201);

        $this->postJson('/api/v1/version/downloaded', $this->payload(), $this->clientHeaders())
            ->assertStatus(200)
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, VersionDownload::count());
    }

    public function test_same_install_new_version_creates_new_row(): void
    {
        $this->postJson('/api/v1/version/downloaded', $this->payload(), $this->clientHeaders())
            ->assertStatus(201);

        $this->postJson('/api/v1/version/downloaded', $this->payload([
            'from_version' => '0.5.32-alpha',
            'to_version' => '0.5.33-alpha',
        ]), $this->clientHeaders())->assertStatus(201);

        $this->assertSame(2, VersionDownload::count());
    }

    public function test_validation_requires_to_version(): void
    {
        $this->postJson('/api/v1/version/downloaded', ['from_version' => '0.5.31-alpha'], $this->clientHeaders())
            ->assertStatus(422);
    }

    public function test_from_version_is_optional(): void
    {
        $this->postJson('/api/v1/version/downloaded', ['to_version' => '0.5.32-alpha'], $this->clientHeaders())
            ->assertStatus(201);

        $this->assertNull(VersionDownload::first()->from_version);
    }

    public function test_admin_adoption_counts_distinct_installs(): void
    {
        $otherAnon = (string) Str::uuid();

        // Dos installs descargan 0.5.32; uno repite (no debe inflar).
        $this->postJson('/api/v1/version/downloaded', $this->payload(), $this->clientHeaders())->assertStatus(201);
        $this->postJson('/api/v1/version/downloaded', $this->payload(), $this->clientHeaders())->assertStatus(200);
        $this->postJson('/api/v1/version/downloaded', $this->payload(), $this->clientHeaders($otherAnon))->assertStatus(201);

        // Y uno de ellos también descarga 0.5.33.
        $this->postJson('/api/v1/version/downloaded', $this->payload([
            'from_version' => '0.5.32-alpha',
            'to_version' => '0.5.33-alpha',
        ]), $this->clientHeaders())->assertStatus(201);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('test1234'),
            'role_id' => $role->id,
        ]);
        Sanctum::actingAs($admin, ['release:upload']);

        $response = $this->getJson('/api/v1/admin/version/adoption');
        $response->assertStatus(200)->assertJsonPath('total_versions', 2);

        $byVersion = collect($response->json('versions'))->keyBy('to_version');
        $this->assertSame(2, $byVersion['0.5.32-alpha']['installs']);
        $this->assertSame(2, $byVersion['0.5.32-alpha']['downloads']);
        $this->assertSame(1, $byVersion['0.5.33-alpha']['installs']);

        $filtered = $this->getJson('/api/v1/admin/version/adoption?version=0.5.33-alpha');
        $filtered->assertStatus(200)->assertJsonPath('total_versions', 1);
        $this->assertSame('0.5.33-alpha', $filtered->json('versions.0.to_version'));
    }

    public function test_admin_adoption_requires_sanctum(): void
    {
        $this->getJson('/api/v1/admin/version/adoption')->assertStatus(401);
    }
}
