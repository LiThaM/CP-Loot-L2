<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Personajes L2 en tu perfil:**

- En `/profile` puedes registrar el **personaje principal** (tu nick L2 actual + clase + nivel) y todos los **personajes secundarios** que quieras.
- Cada personaje tiene nick, raza (Human / Elf / Dark Elf / Orc / Dwarf / Kamael), clase L2 (catálogo completo Interlude) y nivel opcional.
- Al reportar un farm, el líder ve un selector pequeño por cada miembro: por defecto se asume el principal, pero si tienes secundarios el líder puede elegir cuál farmeó esta sesión. Las ventas posteriores heredan el char del farm origen automáticamente.
- Las clases están encadenadas: al elegir raza el selector solo muestra las clases compatibles.
MD;

        $bodyEn = <<<'MD'
**L2 characters in your profile:**

- On `/profile` you can register your **main character** (current nick + class + level) and as many **secondary characters** as you want.
- Each character has nick, race (Human / Elf / Dark Elf / Orc / Dwarf / Kamael), L2 class (full Interlude catalogue) and optional level.
- When reporting a farm, the leader gets a small picker per member: defaults to the main, but if you have secondaries the leader can pick which character farmed this run. Sales propagate the character from the source farm automatically.
- Classes are race-gated: picking a race filters the class dropdown to the compatible options.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Profile: L2 characters'],
            [
                'audience' => 'web',
                'title_es' => 'Perfil: personajes L2',
                'title_en' => 'Profile: L2 characters',
                'body_es' => $bodyEs,
                'body_en' => $bodyEn,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_en', 'Profile: L2 characters')
            ->delete();
    }
};
