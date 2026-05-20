<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Application\Middleware\RequireClientKey;
use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequireClientKeyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([RequireClientKey::class])
            ->post('/_test/client_key', fn () => response()->json(['ok' => true]));
    }

    public function test_missing_key_returns_401(): void
    {
        $this->postJson('/_test/client_key')
            ->assertStatus(401)
            ->assertJsonPath('error', 'missing_client_key');
    }

    public function test_unknown_key_returns_401(): void
    {
        $this->postJson('/_test/client_key', [], [
            'X-Client-Key' => 'totally-random-key-that-does-not-exist',
        ])->assertStatus(401)
          ->assertJsonPath('error', 'invalid_client_key');
    }

    public function test_inactive_key_returns_401(): void
    {
        $raw = 'lu4_dev_' . Str::random(40);
        ClientApiKey::create([
            'key_hash' => ClientApiKey::hash($raw),
            'label' => 'revoked',
            'active' => false,
        ]);

        $this->postJson('/_test/client_key', [], ['X-Client-Key' => $raw])
            ->assertStatus(401);
    }

    public function test_expired_key_returns_401(): void
    {
        $raw = 'lu4_dev_' . Str::random(40);
        ClientApiKey::create([
            'key_hash' => ClientApiKey::hash($raw),
            'label' => 'expired',
            'active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson('/_test/client_key', [], ['X-Client-Key' => $raw])
            ->assertStatus(401);
    }

    public function test_valid_active_key_passes(): void
    {
        $raw = 'lu4_dev_' . Str::random(40);
        $key = ClientApiKey::create([
            'key_hash' => ClientApiKey::hash($raw),
            'label' => 'dev',
            'active' => true,
        ]);

        $this->postJson('/_test/client_key', [], ['X-Client-Key' => $raw])
            ->assertStatus(200)
            ->assertJsonPath('ok', true);

        $this->assertSame(1, $key->fresh()->use_count);
    }
}
