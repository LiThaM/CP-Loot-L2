<?php

namespace Database\Seeders;

use App\Contexts\ClientApi\Domain\Models\Release;
use Illuminate\Database\Seeder;

class InitialReleaseSeeder extends Seeder
{
    public function run(): void
    {
        Release::updateOrCreate(
            ['version' => '0.5.0-alpha'],
            [
                'name' => 'AdenaLedgerStats 0.5.0-alpha',
                'channel' => 'stable',
                'critical_update' => false,
                'min_supported_version' => '0.4.0-alpha',
                'release_notes_md' => "Initial release tracked by the backend.\n\nNotas vendrán cuando se publique la primera build firmada vía panel /system/releases.",
                'released_at' => now(),
                'published_at' => now(),
            ]
        );
    }
}
