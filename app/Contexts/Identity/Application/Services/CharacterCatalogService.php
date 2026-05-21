<?php

namespace App\Contexts\Identity\Application\Services;

use App\Contexts\Identity\Domain\Models\L2Class;

/**
 * Canonical L2 races + class tree for Interlude / LU4. Used as the single
 * source of truth for:
 *   - the `l2_classes` seeder (DB)
 *   - the dropdowns/validation in the user profile (UI)
 *   - the race/class consistency check on Character save
 *
 * Adding a new class? Add it here, run `php artisan db:seed
 * --class=L2ClassSeeder --force` and the rest picks it up automatically.
 */
class CharacterCatalogService
{
    public const RACES = ['Human', 'Elf', 'Dark Elf', 'Orc', 'Dwarf', 'Kamael'];

    /**
     * @var array<int,array{code:string,name:string,race:string,class_type:string,parent_code:?string}>
     */
    public const CLASSES = [
        // -------------------- HUMAN --------------------
        ['code' => 'human_fighter',           'name' => 'Human Fighter',     'race' => 'Human', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'human_warrior',           'name' => 'Warrior',           'race' => 'Human', 'class_type' => '2nd', 'parent_code' => 'human_fighter'],
        ['code' => 'human_gladiator',         'name' => 'Gladiator',         'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_warrior'],
        ['code' => 'human_warlord',           'name' => 'Warlord',           'race' => 'Human', 'class_type' => '2nd', 'parent_code' => 'human_fighter'],
        ['code' => 'human_dreadnought',       'name' => 'Dreadnought',       'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_warlord'],
        ['code' => 'human_knight',            'name' => 'Knight',            'race' => 'Human', 'class_type' => '2nd', 'parent_code' => 'human_fighter'],
        ['code' => 'human_paladin',           'name' => 'Paladin',           'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_knight'],
        ['code' => 'human_dark_avenger',      'name' => 'Dark Avenger',      'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_knight'],
        ['code' => 'human_rogue',             'name' => 'Rogue',             'race' => 'Human', 'class_type' => '2nd', 'parent_code' => 'human_fighter'],
        ['code' => 'human_treasure_hunter',   'name' => 'Treasure Hunter',   'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_rogue'],
        ['code' => 'human_mystic',            'name' => 'Human Mystic',      'race' => 'Human', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'human_wizard',            'name' => 'Wizard',            'race' => 'Human', 'class_type' => '2nd', 'parent_code' => 'human_mystic'],
        ['code' => 'human_sorcerer',          'name' => 'Sorcerer',          'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_wizard'],
        ['code' => 'human_necromancer',       'name' => 'Necromancer',       'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_wizard'],
        ['code' => 'human_warlock',           'name' => 'Warlock',           'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_wizard'],
        ['code' => 'human_cleric',            'name' => 'Cleric',            'race' => 'Human', 'class_type' => '2nd', 'parent_code' => 'human_mystic'],
        ['code' => 'human_bishop',            'name' => 'Bishop',            'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_cleric'],
        ['code' => 'human_prophet',           'name' => 'Prophet',           'race' => 'Human', 'class_type' => '3rd', 'parent_code' => 'human_cleric'],

        // -------------------- ELF --------------------
        ['code' => 'elf_fighter',             'name' => 'Elven Fighter',     'race' => 'Elf', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'elf_knight',              'name' => 'Elven Knight',      'race' => 'Elf', 'class_type' => '2nd', 'parent_code' => 'elf_fighter'],
        ['code' => 'elf_temple_knight',       'name' => 'Temple Knight',     'race' => 'Elf', 'class_type' => '3rd', 'parent_code' => 'elf_knight'],
        ['code' => 'elf_swordsinger',         'name' => 'Sword Singer',      'race' => 'Elf', 'class_type' => '3rd', 'parent_code' => 'elf_knight'],
        ['code' => 'elf_scout',               'name' => 'Elven Scout',       'race' => 'Elf', 'class_type' => '2nd', 'parent_code' => 'elf_fighter'],
        ['code' => 'elf_plainswalker',        'name' => 'Plains Walker',     'race' => 'Elf', 'class_type' => '3rd', 'parent_code' => 'elf_scout'],
        ['code' => 'elf_silver_ranger',       'name' => 'Silver Ranger',     'race' => 'Elf', 'class_type' => '3rd', 'parent_code' => 'elf_scout'],
        ['code' => 'elf_mystic',              'name' => 'Elven Mystic',      'race' => 'Elf', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'elf_wizard',              'name' => 'Elven Wizard',      'race' => 'Elf', 'class_type' => '2nd', 'parent_code' => 'elf_mystic'],
        ['code' => 'elf_spellsinger',         'name' => 'Spellsinger',       'race' => 'Elf', 'class_type' => '3rd', 'parent_code' => 'elf_wizard'],
        ['code' => 'elf_elemental_summoner',  'name' => 'Elemental Summoner','race' => 'Elf', 'class_type' => '3rd', 'parent_code' => 'elf_wizard'],
        ['code' => 'elf_oracle',              'name' => 'Elven Oracle',      'race' => 'Elf', 'class_type' => '2nd', 'parent_code' => 'elf_mystic'],
        ['code' => 'elf_elder',               'name' => 'Elven Elder',       'race' => 'Elf', 'class_type' => '3rd', 'parent_code' => 'elf_oracle'],

        // -------------------- DARK ELF --------------------
        ['code' => 'delf_fighter',            'name' => 'Dark Fighter',      'race' => 'Dark Elf', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'delf_palus_knight',       'name' => 'Palus Knight',      'race' => 'Dark Elf', 'class_type' => '2nd', 'parent_code' => 'delf_fighter'],
        ['code' => 'delf_shillien_knight',    'name' => 'Shillien Knight',   'race' => 'Dark Elf', 'class_type' => '3rd', 'parent_code' => 'delf_palus_knight'],
        ['code' => 'delf_bladedancer',        'name' => 'Bladedancer',       'race' => 'Dark Elf', 'class_type' => '3rd', 'parent_code' => 'delf_palus_knight'],
        ['code' => 'delf_assassin',           'name' => 'Assassin',          'race' => 'Dark Elf', 'class_type' => '2nd', 'parent_code' => 'delf_fighter'],
        ['code' => 'delf_abyss_walker',       'name' => 'Abyss Walker',      'race' => 'Dark Elf', 'class_type' => '3rd', 'parent_code' => 'delf_assassin'],
        ['code' => 'delf_phantom_ranger',     'name' => 'Phantom Ranger',    'race' => 'Dark Elf', 'class_type' => '3rd', 'parent_code' => 'delf_assassin'],
        ['code' => 'delf_mystic',             'name' => 'Dark Mystic',       'race' => 'Dark Elf', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'delf_wizard',             'name' => 'Dark Wizard',       'race' => 'Dark Elf', 'class_type' => '2nd', 'parent_code' => 'delf_mystic'],
        ['code' => 'delf_spellhowler',        'name' => 'Spellhowler',       'race' => 'Dark Elf', 'class_type' => '3rd', 'parent_code' => 'delf_wizard'],
        ['code' => 'delf_phantom_summoner',   'name' => 'Phantom Summoner',  'race' => 'Dark Elf', 'class_type' => '3rd', 'parent_code' => 'delf_wizard'],
        ['code' => 'delf_shillien_oracle',    'name' => 'Shillien Oracle',   'race' => 'Dark Elf', 'class_type' => '2nd', 'parent_code' => 'delf_mystic'],
        ['code' => 'delf_shillien_elder',     'name' => 'Shillien Elder',    'race' => 'Dark Elf', 'class_type' => '3rd', 'parent_code' => 'delf_shillien_oracle'],

        // -------------------- ORC --------------------
        ['code' => 'orc_fighter',             'name' => 'Orc Fighter',       'race' => 'Orc', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'orc_raider',              'name' => 'Orc Raider',        'race' => 'Orc', 'class_type' => '2nd', 'parent_code' => 'orc_fighter'],
        ['code' => 'orc_destroyer',           'name' => 'Destroyer',         'race' => 'Orc', 'class_type' => '3rd', 'parent_code' => 'orc_raider'],
        ['code' => 'orc_monk',                'name' => 'Orc Monk',          'race' => 'Orc', 'class_type' => '2nd', 'parent_code' => 'orc_fighter'],
        ['code' => 'orc_tyrant',              'name' => 'Tyrant',            'race' => 'Orc', 'class_type' => '3rd', 'parent_code' => 'orc_monk'],
        ['code' => 'orc_mystic',              'name' => 'Orc Mystic',        'race' => 'Orc', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'orc_shaman',              'name' => 'Orc Shaman',        'race' => 'Orc', 'class_type' => '2nd', 'parent_code' => 'orc_mystic'],
        ['code' => 'orc_overlord',            'name' => 'Overlord',          'race' => 'Orc', 'class_type' => '3rd', 'parent_code' => 'orc_shaman'],
        ['code' => 'orc_warcryer',            'name' => 'Warcryer',          'race' => 'Orc', 'class_type' => '3rd', 'parent_code' => 'orc_shaman'],

        // -------------------- DWARF --------------------
        ['code' => 'dwarf_apprentice',        'name' => 'Dwarven Apprentice','race' => 'Dwarf', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'dwarf_scavenger',         'name' => 'Scavenger',         'race' => 'Dwarf', 'class_type' => '2nd', 'parent_code' => 'dwarf_apprentice'],
        ['code' => 'dwarf_bounty_hunter',     'name' => 'Bounty Hunter',     'race' => 'Dwarf', 'class_type' => '3rd', 'parent_code' => 'dwarf_scavenger'],
        ['code' => 'dwarf_artisan',           'name' => 'Artisan',           'race' => 'Dwarf', 'class_type' => '2nd', 'parent_code' => 'dwarf_apprentice'],
        ['code' => 'dwarf_warsmith',          'name' => 'Warsmith',          'race' => 'Dwarf', 'class_type' => '3rd', 'parent_code' => 'dwarf_artisan'],

        // -------------------- KAMAEL --------------------
        ['code' => 'kamael_male_soldier',     'name' => 'Kamael Male Soldier',  'race' => 'Kamael', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'kamael_trooper',          'name' => 'Trooper',              'race' => 'Kamael', 'class_type' => '2nd', 'parent_code' => 'kamael_male_soldier'],
        ['code' => 'kamael_warder',           'name' => 'Warder',               'race' => 'Kamael', 'class_type' => '2nd', 'parent_code' => 'kamael_male_soldier'],
        ['code' => 'kamael_inspector',        'name' => 'Inspector',            'race' => 'Kamael', 'class_type' => '3rd', 'parent_code' => null],
        ['code' => 'kamael_berserker',        'name' => 'Berserker',            'race' => 'Kamael', 'class_type' => '3rd', 'parent_code' => 'kamael_trooper'],
        ['code' => 'kamael_male_soulbreaker', 'name' => 'Male Soul Breaker',    'race' => 'Kamael', 'class_type' => '3rd', 'parent_code' => 'kamael_trooper'],
        ['code' => 'kamael_arbalester',       'name' => 'Arbalester',           'race' => 'Kamael', 'class_type' => '3rd', 'parent_code' => 'kamael_warder'],
        ['code' => 'kamael_female_soldier',   'name' => 'Kamael Female Soldier','race' => 'Kamael', 'class_type' => '1st', 'parent_code' => null],
        ['code' => 'kamael_female_soulbreaker', 'name' => 'Female Soul Breaker', 'race' => 'Kamael', 'class_type' => '3rd', 'parent_code' => 'kamael_female_soldier'],
        ['code' => 'kamael_doombringer',      'name' => 'Doombringer',          'race' => 'Kamael', 'class_type' => '3rd', 'parent_code' => null],
        ['code' => 'kamael_soul_hound',       'name' => 'Soul Hound',           'race' => 'Kamael', 'class_type' => '3rd', 'parent_code' => null],
    ];

    public function races(): array
    {
        return self::RACES;
    }

    public function classes()
    {
        return L2Class::orderBy('race')->orderBy('class_type')->orderBy('name')->get();
    }

    public function classesByRace(string $race)
    {
        return L2Class::where('race', $race)->orderBy('class_type')->orderBy('name')->get();
    }
}
