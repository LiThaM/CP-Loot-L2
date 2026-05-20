<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\System\Domain\Models\ChangelogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReleasesPublishTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('client_blobs');

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('test1234'),
            'role_id' => $role->id,
        ]);
    }

    private function fakeBinary(string $content = 'fake-exe-bytes'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'exe_').'.exe';
        file_put_contents($path, $content);
        return new UploadedFile($path, 'AdenaLedgerStats.exe', 'application/octet-stream', null, true);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.5.0',
            'binary' => $this->fakeBinary(),
        ])->assertStatus(401);
    }

    public function test_token_without_release_upload_ability_is_rejected(): void
    {
        Sanctum::actingAs($this->admin, ['some:other-ability']);

        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.5.0',
            'binary' => $this->fakeBinary(),
        ])->assertStatus(403);
    }

    public function test_token_with_release_upload_creates_release(): void
    {
        Sanctum::actingAs($this->admin, ['release:upload']);

        $bytes = 'fake-exe-bytes';
        $expected = hash('sha256', $bytes);

        $response = $this->postJson('/api/v1/admin/releases', [
            'version' => '0.5.0-alpha',
            'channel' => 'stable',
            'release_notes_md' => "## Changes\n- Bug fix",
            'publish_now' => true,
            'create_changelog_entry' => true,
            'expected_sha256' => $expected,
            'binary' => $this->fakeBinary($bytes),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('version', '0.5.0-alpha')
            ->assertJsonPath('sha256', $expected);

        $release = Release::where('version', '0.5.0-alpha')->first();
        $this->assertNotNull($release);
        $this->assertNotNull($release->published_at);
        Storage::disk('client_blobs')->assertExists($release->storage_path);

        $this->assertSame(1, ChangelogEntry::where('version', '0.5.0-alpha')->count());
    }

    public function test_sha256_mismatch_is_rejected(): void
    {
        Sanctum::actingAs($this->admin, ['release:upload']);

        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.5.0',
            'expected_sha256' => str_repeat('f', 64),
            'binary' => $this->fakeBinary('not matching the hash'),
        ])->assertStatus(422)
          ->assertJsonPath('error', 'sha256_mismatch');
    }

    public function test_uploading_same_version_updates_release(): void
    {
        Sanctum::actingAs($this->admin, ['release:upload']);

        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.5.0',
            'binary' => $this->fakeBinary('v1-bytes'),
        ])->assertStatus(201);

        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.5.0',
            'binary' => $this->fakeBinary('v2-bytes'),
            'publish_now' => true,
        ])->assertStatus(200)
          ->assertJsonPath('status', 'updated');

        $this->assertSame(1, Release::where('version', '0.5.0')->count());
        $release = Release::where('version', '0.5.0')->first();
        $this->assertSame(hash('sha256', 'v2-bytes'), $release->sha256);
        $this->assertNotNull($release->published_at);
    }
}
