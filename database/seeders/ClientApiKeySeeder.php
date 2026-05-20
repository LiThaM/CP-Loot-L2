<?php

namespace Database\Seeders;

use App\Contexts\ClientApi\Domain\Models\ClientApiKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClientApiKeySeeder extends Seeder
{
    public function run(): void
    {
        $envKey = env('CLIENT_API_KEY_DEV');

        if (!$envKey) {
            $envKey = 'lu4_dev_' . Str::random(48);
            $this->command?->warn('CLIENT_API_KEY_DEV not set — generated one-shot dev key:');
            $this->command?->warn('  '.$envKey);
            $this->command?->warn('Copy it into .env as CLIENT_API_KEY_DEV=... and into the Python client.');
        }

        ClientApiKey::updateOrCreate(
            ['key_hash' => ClientApiKey::hash($envKey)],
            [
                'label' => 'dev seeded key',
                'active' => true,
                'version_range' => '*',
                'expires_at' => null,
            ]
        );
    }
}
