<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_returns_ok_when_db_and_storage_are_reachable(): void
    {
        Storage::fake('client_blobs');

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'db' => 'ok',
                'storage' => 'ok',
                'version' => 'v1',
            ]);
    }
}
