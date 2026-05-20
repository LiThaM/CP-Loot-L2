<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Application\Middleware\ResolveAnonToken;
use App\Contexts\ClientApi\Domain\Models\AnonToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResolveAnonTokenMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([ResolveAnonToken::class])
            ->get('/_test/anon', function (\Illuminate\Http\Request $r) {
                $token = $r->attributes->get('anon_token');
                return response()->json([
                    'token_id' => $token?->id,
                    'token_uuid' => $token?->token_uuid,
                    'request_count' => $token?->request_count,
                ]);
            });
    }

    public function test_missing_header_returns_400(): void
    {
        $this->getJson('/_test/anon')
            ->assertStatus(400)
            ->assertJsonPath('error', 'missing_anon_token');
    }

    public function test_invalid_uuid_returns_400(): void
    {
        $this->getJson('/_test/anon', ['X-Anon-Token' => 'not-a-uuid'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_anon_token');
    }

    public function test_valid_uuid_creates_anon_token_row(): void
    {
        $uuid = (string) Str::uuid();

        $this->getJson('/_test/anon', ['X-Anon-Token' => $uuid])
            ->assertStatus(200)
            ->assertJsonPath('token_uuid', strtolower($uuid))
            ->assertJsonPath('request_count', 1);

        $this->assertDatabaseHas('anon_tokens', ['token_uuid' => strtolower($uuid)]);
    }

    public function test_repeated_requests_increment_count(): void
    {
        $uuid = (string) Str::uuid();

        $this->getJson('/_test/anon', ['X-Anon-Token' => $uuid]);
        $this->getJson('/_test/anon', ['X-Anon-Token' => $uuid]);
        $response = $this->getJson('/_test/anon', ['X-Anon-Token' => $uuid]);

        $response->assertJsonPath('request_count', 3);
    }

    public function test_banned_token_returns_423(): void
    {
        $uuid = (string) Str::uuid();
        AnonToken::create([
            'token_uuid' => strtolower($uuid),
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
            'banned_at' => now(),
            'banned_reason' => 'abuse',
        ]);

        $this->getJson('/_test/anon', ['X-Anon-Token' => $uuid])
            ->assertStatus(423)
            ->assertJsonPath('error', 'token_banned');
    }
}
