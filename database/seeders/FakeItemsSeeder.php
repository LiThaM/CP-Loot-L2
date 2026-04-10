<?php

namespace Database\Seeders;

use App\Contexts\Loot\Domain\Models\Item;
use Illuminate\Database\Seeder;

class FakeItemsSeeder extends Seeder
{
    public function run(): void
    {
        $fakes = [
            ['name' => 'Draconic Bow', 'grade' => 'S', 'category' => 'Weapon', 'base_points' => 1000],
            ['name' => 'Imperial Crusader Breastplate', 'grade' => 'S', 'category' => 'Armor', 'base_points' => 1500],
            ['name' => 'Angel Slayer', 'grade' => 'S', 'category' => 'Weapon', 'base_points' => 900],
            ['name' => 'Tateossian Necklace', 'grade' => 'S', 'category' => 'Jewelry', 'base_points' => 1200],
            ['name' => 'Tallum Blade', 'grade' => 'A', 'category' => 'Weapon', 'base_points' => 500],
            ['name' => 'Majestic Leather Armor', 'grade' => 'A', 'category' => 'Armor', 'base_points' => 600],
            ['name' => 'Mithril Alloy', 'grade' => 'None', 'category' => 'Material', 'base_points' => 10],
            ['name' => 'Recipe: Draconic Bow (60%)', 'grade' => 'S', 'category' => 'Recipe', 'base_points' => 450],
            ['name' => 'Heavens Divider', 'grade' => 'S', 'category' => 'Weapon', 'base_points' => 1100],
            ['name' => 'Arcana Mace', 'grade' => 'S', 'category' => 'Weapon', 'base_points' => 1050],
            ['name' => 'Basalt Battlehammer', 'grade' => 'S', 'category' => 'Weapon', 'base_points' => 950],
            ['name' => 'Saint Spear', 'grade' => 'S', 'category' => 'Weapon', 'base_points' => 980],
            ['name' => 'Major Arcana Robe', 'grade' => 'S', 'category' => 'Armor', 'base_points' => 1300],
            ['name' => 'Draconic Leather Armor', 'grade' => 'S', 'category' => 'Armor', 'base_points' => 1400],
            ['name' => 'Earring of Antharas', 'grade' => 'S', 'category' => 'Epic Jewelry', 'base_points' => 5000],
            ['name' => 'Ring of Baium', 'grade' => 'S', 'category' => 'Epic Jewelry', 'base_points' => 4500],
            ['name' => 'Necklace of Valakas', 'grade' => 'S', 'category' => 'Epic Jewelry', 'base_points' => 6000],
            ['name' => 'Frintezzas Necklace', 'grade' => 'S', 'category' => 'Epic Jewelry', 'base_points' => 3000],
            ['name' => 'Sword of Ipos', 'grade' => 'A', 'category' => 'Weapon', 'base_points' => 550],
            ['name' => 'Barakiels Axe', 'grade' => 'A', 'category' => 'Weapon', 'base_points' => 520],
            ['name' => 'Sirras Blade', 'grade' => 'A', 'category' => 'Weapon', 'base_points' => 530],
            ['name' => 'Dreadnought Armor', 'grade' => 'A', 'category' => 'Armor', 'base_points' => 580],
            ['name' => 'Soul Crystal: Stage 13', 'grade' => 'None', 'category' => 'Crystal', 'base_points' => 800],
            ['name' => 'Soul Crystal: Stage 12', 'grade' => 'None', 'category' => 'Crystal', 'base_points' => 400],
        ];

        foreach ($fakes as $fake) {
            Item::firstOrCreate(['name' => $fake['name']], $fake);
        }
    }
}
