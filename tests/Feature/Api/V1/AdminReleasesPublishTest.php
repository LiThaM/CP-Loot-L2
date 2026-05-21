<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
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

    private function fakeBinary(string $content = 'fake-exe-bytes', string $ext = 'exe'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rel_').'.'.$ext;
        file_put_contents($path, $content);
        $mime = $ext === 'zip' ? 'application/zip' : 'application/octet-stream';
        return new UploadedFile($path, 'AdenaLedgerStats.'.$ext, $mime, null, true);
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
            'release_notes_es' => "## Cambios\n- Arreglo bug",
            'release_notes_en' => "## Changes\n- Bug fix",
            'publish_now' => true,
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
        $this->assertSame("## Cambios\n- Arreglo bug", $release->release_notes_es);
        $this->assertSame("## Changes\n- Bug fix", $release->release_notes_en);
        Storage::disk('client_blobs')->assertExists($release->storage_path);
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

    public function test_zip_upload_is_accepted_and_stored_with_zip_extension(): void
    {
        Sanctum::actingAs($this->admin, ['release:upload']);

        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.6.0',
            'binary' => $this->fakeBinary('zipped-bundle-bytes', 'zip'),
            'publish_now' => true,
        ])->assertStatus(201);

        $release = Release::where('version', '0.6.0')->first();
        $this->assertStringEndsWith('.zip', $release->storage_path);
        Storage::disk('client_blobs')->assertExists($release->storage_path);
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        Sanctum::actingAs($this->admin, ['release:upload']);

        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.7.0',
            'binary' => $this->fakeBinary('not-a-real-installer', 'rar'),
        ])->assertStatus(422)
          ->assertJsonPath('error', 'invalid_extension');
    }

    public function test_unified_release_notes_field_seeds_both_locales(): void
    {
        Sanctum::actingAs($this->admin, ['release:upload']);

        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.8.0',
            'release_notes' => "### Added\n- shared notes for both locales",
            'binary' => $this->fakeBinary(),
        ])->assertStatus(201);

        $release = Release::where('version', '0.8.0')->first();
        $this->assertSame("### Added\n- shared notes for both locales", $release->release_notes_md);
        $this->assertSame("### Added\n- shared notes for both locales", $release->release_notes_es);
        $this->assertSame("### Added\n- shared notes for both locales", $release->release_notes_en);
    }

    public function test_uploading_newer_version_auto_publishes_without_publish_now(): void
    {
        Sanctum::actingAs($this->admin, ['release:upload']);

        // Seed a published baseline 0.5.4.
        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.5.4-alpha',
            'binary' => $this->fakeBinary('v1-bytes'),
            'publish_now' => true,
        ])->assertStatus(201);

        // Upload a newer version WITHOUT publish_now; expect auto-promote.
        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.5.5-alpha',
            'binary' => $this->fakeBinary('v2-bytes'),
        ])->assertStatus(201);

        $latest = Release::where('version', '0.5.5-alpha')->first();
        $this->assertNotNull($latest);
        $this->assertNotNull($latest->published_at, 'newer version must auto-publish');
    }

    public function test_re_upload_without_release_notes_preserves_existing_notes(): void
    {
        Sanctum::actingAs($this->admin, ['release:upload']);

        // First upload populates release_notes_md.
        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.9.0',
            'release_notes' => "## Notes\n- initial markdown",
            'binary' => $this->fakeBinary('v1-bytes'),
            'publish_now' => true,
        ])->assertStatus(201);

        // Re-upload the same version without sending release_notes (typical
        // when the build script's changelog extractor returns nothing).
        $this->postJson('/api/v1/admin/releases', [
            'version' => '0.9.0',
            'binary' => $this->fakeBinary('v2-bytes'),
        ])->assertStatus(200)
          ->assertJsonPath('status', 'updated');

        $release = Release::where('version', '0.9.0')->first();
        $this->assertSame("## Notes\n- initial markdown", $release->release_notes_md);
        $this->assertSame("## Notes\n- initial markdown", $release->release_notes_es);
        $this->assertSame("## Notes\n- initial markdown", $release->release_notes_en);
    }
}
