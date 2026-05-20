<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\Telemetry\Domain\Models\OcrSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmitOcrSampleTest extends TestCase
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

    private function headers(): array
    {
        return ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid];
    }

    private function makePng(int $w = 40, int $h = 12): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);
        $path = tempnam(sys_get_temp_dir(), 'ocr_').'.png';
        file_put_contents($path, $bytes);
        return new UploadedFile($path, 'sample.png', 'image/png', null, true);
    }

    public function test_happy_path_creates_sample(): void
    {
        $response = $this->postJson('/api/v1/ocr/samples', [
            'image' => $this->makePng(),
            'ground_truth' => '12345/67890',
            'category' => 'bar',
            'actual_ocr' => '12345/67800',
            'confidence' => 0.85,
        ], $this->headers());

        $response->assertStatus(201)
            ->assertJsonPath('status', 'accepted');

        $this->assertSame(1, OcrSample::count());
    }

    public function test_duplicate_image_returns_200_with_existing_id(): void
    {
        $png = $this->makePng();

        $first = $this->postJson('/api/v1/ocr/samples', [
            'image' => $png,
            'ground_truth' => '50/100',
            'category' => 'bar',
        ], $this->headers());
        $first->assertStatus(201);

        // Re-upload same bytes
        $second = $this->postJson('/api/v1/ocr/samples', [
            'image' => $this->makePng(), // same bytes (empty truecolor of same size)
            'ground_truth' => '50/100',
            'category' => 'bar',
        ], $this->headers());

        $second->assertStatus(200)
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, OcrSample::count());
    }

    public function test_ground_truth_with_chat_pattern_is_rejected(): void
    {
        $this->postJson('/api/v1/ocr/samples', [
            'image' => $this->makePng(),
            'ground_truth' => 'Antenita: hello there friend',
            'category' => 'chat',
        ], $this->headers())->assertStatus(422)
          ->assertJsonValidationErrors('ground_truth');
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->postJson('/api/v1/ocr/samples', [
            'image' => $this->makePng(),
            'ground_truth' => '50/100',
            'category' => 'invalid_cat',
        ], $this->headers())->assertStatus(422);
    }
}
