<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\Telemetry\Application\Requests\UploadDigitTemplatesRequest;
use App\Contexts\Telemetry\Domain\Models\DigitTemplateSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class UploadDigitTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private string $clientKey;
    private string $anonUuid;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('client_blobs');

        $this->clientKey = 'lu4_test_' . Str::random(40);
        ClientApiKey::create([
            'key_hash' => ClientApiKey::hash($this->clientKey),
            'label' => 'test',
            'active' => true,
        ]);

        $this->anonUuid = (string) Str::uuid();
    }

    private function headers(?string $clientKey = null, ?string $anonToken = null): array
    {
        return array_filter([
            'X-Client-Key' => $clientKey ?? $this->clientKey,
            'X-Anon-Token' => $anonToken ?? $this->anonUuid,
        ]);
    }

    private function makePngBytes(int $w, int $h): string
    {
        $image = imagecreatetruecolor($w, $h);
        imagefilledrectangle($image, 0, 0, $w - 1, $h - 1, imagecolorallocate($image, 80, 80, 80));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }

    private function makeZipWith(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl_').'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }
        $zip->close();
        return $path;
    }

    public function test_happy_path_accepts_valid_png_entries(): void
    {
        $png = $this->makePngBytes(20, 32);
        $zipPath = $this->makeZipWith([
            '0_0.png' => $png,
            '1_0.png' => $png,
            'slash_0.png' => $png,
        ]);

        $upload = new UploadedFile($zipPath, 'templates.zip', 'application/zip', null, true);

        $response = $this->postJson('/api/v1/templates/digits', [
            'templates' => $upload,
        ], $this->headers());

        $response->assertStatus(201)
            ->assertJsonPath('accepted', 3)
            ->assertJsonPath('rejected', 0);

        $this->assertSame(3, DigitTemplateSubmission::count());
        $disk = Storage::disk('client_blobs');
        foreach (DigitTemplateSubmission::all() as $row) {
            $this->assertTrue($disk->exists($row->storage_path));
        }
    }

    public function test_missing_client_key_returns_401(): void
    {
        $png = $this->makePngBytes(20, 32);
        $upload = new UploadedFile(
            $this->makeZipWith(['0_0.png' => $png]),
            'templates.zip',
            'application/zip',
            null,
            true
        );

        $this->postJson('/api/v1/templates/digits', ['templates' => $upload], [
            'X-Anon-Token' => $this->anonUuid,
        ])->assertStatus(401);
    }

    public function test_missing_anon_token_returns_400(): void
    {
        $png = $this->makePngBytes(20, 32);
        $upload = new UploadedFile(
            $this->makeZipWith(['0_0.png' => $png]),
            'templates.zip',
            'application/zip',
            null,
            true
        );

        $this->postJson('/api/v1/templates/digits', ['templates' => $upload], [
            'X-Client-Key' => $this->clientKey,
        ])->assertStatus(400);
    }

    public function test_zip_slip_entry_is_rejected(): void
    {
        $png = $this->makePngBytes(20, 32);
        $zipPath = $this->makeZipWith([
            '0_0.png' => $png,
            '../evil.png' => $png,
        ]);
        $upload = new UploadedFile($zipPath, 't.zip', 'application/zip', null, true);

        $response = $this->postJson('/api/v1/templates/digits', ['templates' => $upload], $this->headers());

        $response->assertStatus(201)
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath('rejected', 1);

        $body = $response->json();
        $this->assertNotEmpty(array_filter($body['reasons'], fn ($r) => str_contains($r, 'zip-slip')));
    }

    public function test_invalid_png_dimensions_are_rejected(): void
    {
        $png = $this->makePngBytes(100, 100);
        $zipPath = $this->makeZipWith(['0_0.png' => $png]);
        $upload = new UploadedFile($zipPath, 't.zip', 'application/zip', null, true);

        $this->postJson('/api/v1/templates/digits', ['templates' => $upload], $this->headers())
            ->assertStatus(201)
            ->assertJsonPath('accepted', 0)
            ->assertJsonPath('rejected', 1);
    }

    public function test_filename_with_invalid_char_is_rejected(): void
    {
        $png = $this->makePngBytes(20, 32);
        $zipPath = $this->makeZipWith(['x_0.png' => $png]);
        $upload = new UploadedFile($zipPath, 't.zip', 'application/zip', null, true);

        $this->postJson('/api/v1/templates/digits', ['templates' => $upload], $this->headers())
            ->assertStatus(201)
            ->assertJsonPath('accepted', 0)
            ->assertJsonPath('rejected', 1);
    }

    public function test_quota_exceeded_returns_429(): void
    {
        $token = AnonToken::create([
            'token_uuid' => strtolower($this->anonUuid),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        for ($i = 0; $i < UploadDigitTemplatesRequest::PER_ANON_TOKEN_QUOTA; $i++) {
            DigitTemplateSubmission::create([
                'anon_token_id' => $token->id,
                'char' => '0',
                'storage_path' => "fake/path_{$i}.png",
                'dim_w' => 20,
                'dim_h' => 32,
                'original_size_bytes' => 100,
                'submitted_at' => now(),
            ]);
        }

        $png = $this->makePngBytes(20, 32);
        $upload = new UploadedFile(
            $this->makeZipWith(['0_0.png' => $png]),
            't.zip',
            'application/zip',
            null,
            true
        );

        $this->postJson('/api/v1/templates/digits', ['templates' => $upload], $this->headers())
            ->assertStatus(429)
            ->assertJsonPath('error', 'quota_exceeded');
    }

    public function test_non_png_entry_is_rejected(): void
    {
        $png = $this->makePngBytes(20, 32);
        $zipPath = $this->makeZipWith([
            '0_0.png' => $png,
            'readme.txt' => 'not a png',
        ]);
        $upload = new UploadedFile($zipPath, 't.zip', 'application/zip', null, true);

        $this->postJson('/api/v1/templates/digits', ['templates' => $upload], $this->headers())
            ->assertStatus(201)
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath('rejected', 1);
    }
}
