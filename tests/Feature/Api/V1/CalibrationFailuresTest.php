<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Telemetry\Domain\Models\CalibrationFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalibrationFailuresTest extends TestCase
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

    private function clientHeaders(): array
    {
        return ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid];
    }

    /** PNG 1x1 válido (magic bytes + IHDR reales). */
    private function fakePng(): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $path = tempnam(sys_get_temp_dir(), 'png_').'.png';
        file_put_contents($path, $png);
        return new UploadedFile($path, 'frame.png', 'image/png', null, true);
    }

    public function test_requires_client_key_and_anon_token(): void
    {
        $this->postJson('/api/v1/calibration/failures', ['meta' => '{}'])->assertStatus(401);
    }

    public function test_store_calibrator_report_without_kind_defaults_to_calibrator(): void
    {
        $meta = [
            'ts' => '20260611_120000',
            'rect' => [10, 20, 300, 80],
            'game_size' => [1920, 1080],
            'screen_size' => [1920, 1080],
            'app_version' => '0.5.31-alpha',
        ];

        $response = $this->post('/api/v1/calibration/failures', [
            'meta' => json_encode($meta),
            'image' => $this->fakePng(),
        ], $this->clientHeaders());

        $response->assertStatus(201)->assertJsonPath('status', 'accepted');

        $failure = CalibrationFailure::first();
        $this->assertSame('calibrator', $failure->kind);
        $this->assertSame('0.5.31-alpha', $failure->app_version);
        $this->assertSame([10, 20, 300, 80], $failure->meta_json['rect']);
        $this->assertNotNull($failure->image_path);
        $this->assertTrue(Storage::disk('client_blobs')->exists($failure->image_path));
    }

    public function test_store_runtime_zero_readings_without_image(): void
    {
        $meta = [
            'kind' => 'runtime_zero_readings',
            'zeros' => ['hp', 'mp', 'cp'],
            'char' => 'Antenita',
            'app_version' => '0.5.31-alpha',
            'readings' => ['hp' => 0, 'mp' => 0, 'cp' => 0, 'level' => 52],
            'calibration' => ['hp_rect' => [1, 2, 3, 4]],
            'game_size' => [1924, 1061],
            'screen_size' => [1920, 1080],
            'tesseract' => true,
            'wgc' => true,
        ];

        $response = $this->post('/api/v1/calibration/failures', [
            'meta' => json_encode($meta),
        ], $this->clientHeaders());

        $response->assertStatus(201);

        $failure = CalibrationFailure::first();
        $this->assertSame('runtime_zero_readings', $failure->kind);
        $this->assertSame('Antenita', $failure->char_name);
        $this->assertNull($failure->image_path);
        $this->assertSame([1924, 1061], $failure->meta_json['game_size']);
    }

    public function test_store_rejects_invalid_meta_json(): void
    {
        $this->post('/api/v1/calibration/failures', [
            'meta' => '{not json',
        ], $this->clientHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_meta_json');
    }

    public function test_store_rejects_non_png_image(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jpg_').'.png';
        file_put_contents($path, 'definitely-not-png');
        $file = new UploadedFile($path, 'frame.png', 'image/png', null, true);

        $this->post('/api/v1/calibration/failures', [
            'meta' => '{}',
            'image' => $file,
        ], $this->clientHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_png');
    }

    public function test_admin_index_filters_by_kind_and_serves_image(): void
    {
        $this->post('/api/v1/calibration/failures', [
            'meta' => json_encode(['kind' => 'runtime_zero_readings', 'char' => 'A']),
            'image' => $this->fakePng(),
        ], $this->clientHeaders())->assertStatus(201);

        $this->post('/api/v1/calibration/failures', [
            'meta' => json_encode(['rect' => [1, 2, 3, 4]]),
        ], $this->clientHeaders())->assertStatus(201);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('test1234'),
            'role_id' => $role->id,
        ]);
        Sanctum::actingAs($admin, ['release:upload']);

        $all = $this->getJson('/api/v1/admin/calibration/failures');
        $all->assertStatus(200)->assertJsonPath('total', 2);

        $filtered = $this->getJson('/api/v1/admin/calibration/failures?kind=runtime_zero_readings');
        $filtered->assertStatus(200)->assertJsonPath('total', 1);
        $this->assertSame('runtime_zero_readings', $filtered->json('failures.0.kind'));
        $this->assertNotNull($filtered->json('failures.0.image_url'));

        $imageId = $filtered->json('failures.0.id');
        $image = $this->get("/api/v1/admin/calibration/failures/{$imageId}/image");
        $image->assertStatus(200)->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $image->streamedContent());
    }

    public function test_admin_endpoints_require_sanctum(): void
    {
        $this->getJson('/api/v1/admin/calibration/failures')->assertStatus(401);
        $this->getJson('/api/v1/admin/calibration/failures/1/image')->assertStatus(401);
    }
}
