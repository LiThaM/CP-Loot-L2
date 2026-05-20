<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\ClientApi\Domain\Models\CrashReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrashReportTest extends TestCase
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

    private function headers(): array
    {
        return ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid];
    }

    public function test_happy_path_stores_crash_with_fingerprint(): void
    {
        $stack = "Traceback (most recent call last):\n  File \"C:\\Program Files\\Lu4\\main.py\", line 42, in <module>\n    overlay.run()\nValueError: bad input";

        $response = $this->postJson('/api/v1/crashes', [
            'bot_version' => '0.5.0-alpha',
            'os_version' => 'Windows-11-26100',
            'python_version' => '3.12.10',
            'message' => 'overlay startup failed',
            'stack_trace' => $stack,
            'context' => ['layout' => 'vertical'],
        ], $this->headers());

        $response->assertStatus(201)
            ->assertJsonPath('status', 'accepted');

        $this->assertSame(1, CrashReport::count());
        $crash = CrashReport::first();
        $this->assertSame('overlay startup failed', $crash->message);
        $this->assertSame(64, strlen($crash->fingerprint));
    }

    public function test_same_stack_in_different_paths_produces_same_fingerprint(): void
    {
        $stackA = "Traceback:\n  File \"C:\\Users\\alice\\app\\main.py\", line 10\nValueError: x";
        $stackB = "Traceback:\n  File \"D:\\bots\\AdenaLedger\\main.py\", line 99\nValueError: x";

        $this->postJson('/api/v1/crashes', [
            'bot_version' => '0.5.0',
            'stack_trace' => $stackA,
        ], $this->headers())->assertStatus(201);

        $this->postJson('/api/v1/crashes', [
            'bot_version' => '0.5.0',
            'stack_trace' => $stackB,
        ], $this->headers())->assertStatus(201);

        $fingerprints = CrashReport::pluck('fingerprint')->unique();
        $this->assertCount(1, $fingerprints, 'Same logical crash should share fingerprint despite different paths/lines.');
    }

    public function test_missing_stack_trace_is_rejected(): void
    {
        $this->postJson('/api/v1/crashes', [
            'bot_version' => '0.5.0',
        ], $this->headers())->assertStatus(422)
          ->assertJsonValidationErrors('stack_trace');
    }
}
