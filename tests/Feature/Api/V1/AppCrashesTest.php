<?php

namespace Tests\Feature\Api\V1;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use App\Contexts\ClientApi\Domain\Models\CrashReport;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppCrashesTest extends TestCase
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

    private function clientHeaders(): array
    {
        return ['X-Client-Key' => $this->clientKey, 'X-Anon-Token' => $this->anonUuid];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'app_version' => '0.5.31-alpha',
            'char' => 'Antenita',
            'os_version' => 'Windows-10-10.0.19045',
            'traceback' => "Traceback (most recent call last):\n  File \"C:\\app\\tools\\overlay.py\", line 123, in _tick\n    boom()\nZeroDivisionError: division by zero",
            'context' => ['window' => 'overlay', 'action' => 'tick'],
            'ts' => 1765400000.5,
        ], $overrides);
    }

    public function test_requires_client_key_and_anon_token(): void
    {
        $this->postJson('/api/v1/app/crashes', $this->payload())->assertStatus(401);
    }

    public function test_store_creates_crash_report(): void
    {
        $response = $this->postJson('/api/v1/app/crashes', $this->payload(), $this->clientHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('occurrences', 1);

        $crash = CrashReport::first();
        $this->assertSame('0.5.31-alpha', $crash->app_version);
        $this->assertSame('Antenita', $crash->char_name);
        $this->assertNotNull($crash->client_ts);
        $this->assertSame(['window' => 'overlay', 'action' => 'tick'], $crash->context_json);
    }

    public function test_same_traceback_same_version_deduplicates(): void
    {
        $this->postJson('/api/v1/app/crashes', $this->payload(), $this->clientHeaders())
            ->assertStatus(201);

        // Mismo crash en otra máquina/path/línea → mismo fingerprint.
        $variant = $this->payload([
            'traceback' => "Traceback (most recent call last):\n  File \"D:\\otra\\ruta\\tools\\overlay.py\", line 999, in _tick\n    boom()\nZeroDivisionError: division by zero",
            'char' => 'OtroChar',
        ]);
        $response = $this->postJson('/api/v1/app/crashes', $variant, $this->clientHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('status', 'deduplicated')
            ->assertJsonPath('occurrences', 2);

        $this->assertSame(1, CrashReport::count());
        $this->assertSame('OtroChar', CrashReport::first()->char_name);
    }

    public function test_same_traceback_new_version_creates_new_row(): void
    {
        $this->postJson('/api/v1/app/crashes', $this->payload(), $this->clientHeaders())
            ->assertStatus(201);

        $this->postJson('/api/v1/app/crashes', $this->payload(['app_version' => '0.5.32-alpha']), $this->clientHeaders())
            ->assertStatus(201);

        $this->assertSame(2, CrashReport::count());
    }

    public function test_validation_requires_traceback_and_version(): void
    {
        $this->postJson('/api/v1/app/crashes', ['char' => 'X'], $this->clientHeaders())
            ->assertStatus(422);
    }

    public function test_admin_index_lists_and_filters_by_version(): void
    {
        $this->postJson('/api/v1/app/crashes', $this->payload(), $this->clientHeaders())->assertStatus(201);
        $this->postJson('/api/v1/app/crashes', $this->payload([
            'app_version' => '0.5.30-alpha',
            'traceback' => "Traceback:\nKeyError: 'hp'",
        ]), $this->clientHeaders())->assertStatus(201);

        // Crash legacy del bot (bot_version, sin app_version) no debe salir.
        CrashReport::create([
            'bot_version' => '1.0.0',
            'fingerprint' => str_repeat('a', 64),
            'stack_trace' => 'legacy',
            'reported_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('test1234'),
            'role_id' => $role->id,
        ]);
        Sanctum::actingAs($admin, ['release:upload']);

        $all = $this->getJson('/api/v1/admin/app/crashes');
        $all->assertStatus(200)->assertJsonPath('total', 2);

        $filtered = $this->getJson('/api/v1/admin/app/crashes?version=0.5.30-alpha');
        $filtered->assertStatus(200)->assertJsonPath('total', 1);
        $this->assertSame('0.5.30-alpha', $filtered->json('crashes.0.app_version'));
    }

    public function test_admin_index_requires_sanctum(): void
    {
        $this->getJson('/api/v1/admin/app/crashes')->assertStatus(401);
    }
}
