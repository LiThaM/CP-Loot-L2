<?php

namespace Database\Seeders;

use App\Contexts\Identity\Application\Services\CharacterCatalogService;
use App\Contexts\Identity\Domain\Models\L2Class;
use Illuminate\Database\Seeder;

class L2ClassSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CharacterCatalogService::CLASSES as $row) {
            L2Class::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'race' => $row['race'],
                    'class_type' => $row['class_type'],
                    'parent_code' => $row['parent_code'],
                ]
            );
        }
    }
}
