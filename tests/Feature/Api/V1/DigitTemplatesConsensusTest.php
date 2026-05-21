<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\Telemetry\Application\Services\DigitConsensusService;
use App\Contexts\Telemetry\Domain\Models\DigitTemplateSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigitTemplatesConsensusTest extends TestCase
{
    use RefreshDatabase;

    private string $clientKey;

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
    }

    private function makePng(int $brightness = 80): string
    {
        $w = 20; $h = 32;
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, imagecolorallocate($img, $brightness, $brightness, $brightness));
        // Draw a digit-ish shape so phash is meaningful.
        imagefilledrectangle($img, 6, 4, 14, 6, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 6, 26, 14, 28, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 6, 14, 14, 16, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);
        return $bytes;
    }

    private function seedTemplate(AnonToken $token, string $char, string $pngBytes, ?float $sharpness = null, ?string $botVersion = null): DigitTemplateSubmission
    {
        $consensus = app(DigitConsensusService::class);
        $relPath = sprintf('templates/digits/test/%s.png', Str::uuid());
        Storage::disk('client_blobs')->put($relPath, $pngBytes);

        return DigitTemplateSubmission::create([
            'anon_token_id' => $token->id,
            'char' => $char,
            'storage_path' => $relPath,
            'phash' => $consensus->perceptualHash($pngBytes),
            'bot_version' => $botVersion,
            'sharpness' => $sharpness ?? $consensus->sharpness($pngBytes),
            'dim_w' => 20,
            'dim_h' => 32,
            'original_size_bytes' => strlen($pngBytes),
            'submitted_at' => now(),
        ]);
    }

    private function makeAnon(): AnonToken
    {
        return AnonToken::create([
            'token_uuid' => strtolower((string) Str::uuid()),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function headers(?string $anonUuid = null): array
    {
        return [
            'X-Client-Key' => $this->clientKey,
            'X-Anon-Token' => $anonUuid ?? (string) Str::uuid(),
        ];
    }

    public function test_returns_204_when_pool_is_too_small(): void
    {
        $token = $this->makeAnon();
        $this->seedTemplate($token, '0', $this->makePng());

        $this->get('/api/v1/templates/digits', $this->headers())
            ->assertStatus(204);
    }

    public function test_returns_zip_with_etag_when_pool_has_enough_contributors(): void
    {
        $png = $this->makePng();
        for ($i = 0; $i < 4; $i++) {
            $this->seedTemplate($this->makeAnon(), '0', $png);
        }

        $response = $this->get('/api/v1/templates/digits', $this->headers());

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('ETag');

        $this->assertNotEmpty($response->getContent());
    }

    public function test_304_when_if_none_match_matches_cached_etag(): void
    {
        $png = $this->makePng();
        for ($i = 0; $i < 4; $i++) {
            $this->seedTemplate($this->makeAnon(), '0', $png);
        }

        $first = $this->get('/api/v1/templates/digits', $this->headers());
        $etag = $first->headers->get('ETag');

        $this->get('/api/v1/templates/digits', array_merge($this->headers(), ['If-None-Match' => $etag]))
            ->assertStatus(304);
    }

    public function test_rebuild_command_writes_cached_artifact(): void
    {
        $png = $this->makePng();
        for ($i = 0; $i < 3; $i++) {
            $this->seedTemplate($this->makeAnon(), '0', $png);
        }

        $this->artisan('digits:rebuild-consensus')->assertSuccessful();

        $consensus = app(DigitConsensusService::class);
        $this->assertTrue(Storage::disk('client_blobs')->exists($consensus->cachedZipPath(null)));
    }

    public function test_invalid_version_param_returns_422(): void
    {
        $this->get('/api/v1/templates/digits?version=$$bad$$', $this->headers())
            ->assertStatus(422);
    }
}
