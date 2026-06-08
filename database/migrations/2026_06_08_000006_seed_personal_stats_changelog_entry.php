<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Tu propia sección "Yo" llega al menú principal. Encuéntrala como **`Yo ▼`** y ahí tienes:

- **Mi perfil**: cuenta y preferencias.
- **Mis personajes**: alta y edición de tus characters de Lineage II.
- **Mi almacén**: tu adena e items personales (donde estaba antes).
- **Mis estadísticas**: una pantalla nueva [`/profile/stats`](/profile/stats) con KPIs personales, gráficos diarios de puntos y adena, top items que has recibido, tu posición en el ranking del CP, tu posición en el DKP tracker (si tu CP lo tiene activo) y un calendario de actividad.
- **Mis tickets**: tus solicitudes de soporte (movido del menú "More").

De paso reorganizamos: el menú "More" desaparece (Items DB pasa a link principal), cada item de cada dropdown ahora muestra una mini-descripción debajo, y el "CP Vault" pasa a llamarse simplemente "Almacén" dentro del dropdown del CP.
MD;

        $bodyEn = <<<'MD'
Your own "Me" section is now in the main menu. Look for **`Me ▼`** to find:

- **My profile**: account and preferences.
- **My characters**: register and edit your Lineage II characters.
- **My warehouse**: your personal adena and items (used to live under Loot).
- **My stats**: brand-new screen at [`/profile/stats`](/profile/stats) with personal KPIs, daily charts for points and adena, top items you received, your CP rank, your DKP tracker position (if your CP has it on) and an activity calendar.
- **My tickets**: your support requests (moved out of the "More" menu).

Also reorganized: the "More" dropdown is gone (Items DB is now a top-level link), every item in every dropdown shows a short hint underneath, and "CP Vault" inside the CP dropdown was renamed to just "Warehouse" — the dropdown context already tells you it's the CP one.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Personal "Me" section + per-user stats'],
            [
                'audience' => 'web',
                'title_es' => 'Sección "Yo" + estadísticas personales',
                'title_en' => 'Personal "Me" section + per-user stats',
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
            ->where('title_en', 'Personal "Me" section + per-user stats')
            ->delete();
    }
};
