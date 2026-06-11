<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\ClientApi\Domain\Models\GpsMapdataVersion;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GpsMapdataTest extends TestCase
{
    use RefreshDatabase;

    private string $clientKey;
    private string $anonUuid;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('client_blobs');
        $this->clientKey = 'lu4_test_'.Str::random(40);
        ClientApiKey::create(['key_hash' => ClientApiKey::hash($this->clientKey), 'label' => 'test', 'active' => true]);
        $this->anonUuid = (string) Str::uuid();
    }

    private function clientHeaders(bool $withAnon = false): array
    {
        return $withAnon
            ? ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid]
            : ['X-Client-Key' => $this->clientKey];
    }

    /** npz = ZIP: el contenido fake debe empezar con los magic bytes PK. */
    private function fakeNpz(string $payload = 'fp-data'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'npz_').'.npz';
        file_put_contents($path, "PK\x03\x04".$payload);
        return new UploadedFile($path, 'gps_mapdata.npz', 'application/octet-stream', null, true);
    }

    public function test_get_requires_client_key(): void
    {
        $this->getJson('/api/v1/gps/mapdata')->assertStatus(401);
    }

    public function test_get_returns_404_when_nothing_uploaded(): void
    {
        $this->getJson('/api/v1/gps/mapdata', $this->clientHeaders())
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_uploaded');
    }

    public function test_post_then_get_roundtrip_with_etag_304(): void
    {
        $post = $this->post('/api/v1/gps/mapdata', [
            'mapdata' => $this->fakeNpz('roundtrip'),
        ], $this->clientHeaders(withAnon: true));

        $post->assertStatus(201)->assertJsonPath('ok', true);
        $this->assertSame(1, GpsMapdataVersion::count());

        $get = $this->get('/api/v1/gps/mapdata', $this->clientHeaders());
        $get->assertStatus(200)
            ->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertSame("PK\x03\x04roundtrip", $get->streamedContent());

        $etag = $get->headers->get('ETag');
        $this->assertNotNull($etag);

        $this->get('/api/v1/gps/mapdata', array_merge($this->clientHeaders(), [
            'If-None-Match' => $etag,
        ]))->assertStatus(304);
    }

    public function test_post_rejects_non_zip_payload(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad_').'.npz';
        file_put_contents($path, 'not-a-zip-at-all');
        $file = new UploadedFile($path, 'gps_mapdata.npz', 'application/octet-stream', null, true);

        $this->post('/api/v1/gps/mapdata', ['mapdata' => $file], $this->clientHeaders(withAnon: true))
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_npz');
    }

    public function test_post_identical_blob_is_noop(): void
    {
        $this->post('/api/v1/gps/mapdata', ['mapdata' => $this->fakeNpz('same')], $this->clientHeaders(withAnon: true))
            ->assertStatus(201);

        $this->post('/api/v1/gps/mapdata', ['mapdata' => $this->fakeNpz('same')], $this->clientHeaders(withAnon: true))
            ->assertStatus(200)
            ->assertJsonPath('unchanged', true);

        $this->assertSame(1, GpsMapdataVersion::count());
    }

    public function test_versions_are_pruned_to_keep_limit(): void
    {
        // KEEP_VERSIONS=10 — subimos 12 blobs distintos rotando anon tokens
        // para no chocar con el rate limit de 6/hora por install.
        for ($i = 0; $i < 12; $i++) {
            $this->anonUuid = (string) Str::uuid();
            $this->post('/api/v1/gps/mapdata', [
                'mapdata' => $this->fakeNpz("blob-$i"),
            ], $this->clientHeaders(withAnon: true))->assertStatus(201);
        }

        $this->assertSame(10, GpsMapdataVersion::count());

        // El blob actual es el último subido y todas las versiones
        // conservadas tienen su binario en disco.
        $get = $this->get('/api/v1/gps/mapdata', $this->clientHeaders());
        $this->assertSame("PK\x03\x04blob-11", $get->streamedContent());

        GpsMapdataVersion::all()->each(function (GpsMapdataVersion $v) {
            $this->assertTrue(Storage::disk('client_blobs')->exists($v->storage_path));
        });
    }

    public function test_admin_versions_and_revert(): void
    {
        $this->post('/api/v1/gps/mapdata', ['mapdata' => $this->fakeNpz('good')], $this->clientHeaders(withAnon: true))
            ->assertStatus(201);
        $goodId = GpsMapdataVersion::query()->latest('id')->first()->id;

        $this->post('/api/v1/gps/mapdata', ['mapdata' => $this->fakeNpz('garbage')], $this->clientHeaders(withAnon: true))
            ->assertStatus(201);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('test1234'),
            'role_id' => $role->id,
        ]);
        Sanctum::actingAs($admin, ['release:upload']);

        $list = $this->getJson('/api/v1/admin/gps/mapdata/versions');
        $list->assertStatus(200);
        $this->assertCount(2, $list->json('versions'));
        $this->assertTrue($list->json('versions.0.current'));

        $revert = $this->postJson("/api/v1/admin/gps/mapdata/revert/{$goodId}");
        $revert->assertStatus(200)->assertJsonPath('reverted_to', $goodId);

        // El GET público vuelve a servir el contenido bueno.
        $get = $this->get('/api/v1/gps/mapdata', $this->clientHeaders());
        $this->assertSame("PK\x03\x04good", $get->streamedContent());

        $latest = GpsMapdataVersion::query()->latest('id')->first();
        $this->assertSame('revert', $latest->source);
        $this->assertSame($goodId, $latest->reverted_from_id);
    }

    public function test_admin_endpoints_require_sanctum(): void
    {
        $this->getJson('/api/v1/admin/gps/mapdata/versions')->assertStatus(401);
        $this->postJson('/api/v1/admin/gps/mapdata/revert/1')->assertStatus(401);
    }
}
