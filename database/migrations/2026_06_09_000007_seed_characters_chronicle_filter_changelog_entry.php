<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Personajes arreglados y mejorados.** Si entrabas a [/characters](/characters) los desplegables de raza y clase salían vacíos — un desajuste interno entre el backend y el frontend en los nombres de propiedades. Resuelto: ahora cargan las 69 clases canónicas.

**Y de paso, filtrado por crónica.** El catálogo se ajusta automáticamente a la crónica de tu CP:

- CPs en C1, C2, C3, C4, C5, IL o Classic → **sin Kamael** (no existían).
- CPs en CT1, GF, HB o LU4 → **catálogo completo** con Kamael.

Sobre la sección verás un pill informativo: "Catálogo filtrado por la crónica X de tu CP". Si por algo no perteneces a una CP, sale un banner explicándolo — recuerda que la app espera que cada usuario esté en una CP.
MD;

        $bodyEn = <<<'MD'
**Characters fixed and improved.** When opening [/characters](/characters) the race and class dropdowns were coming up empty — an internal naming mismatch between the backend and the frontend props. Fixed: the 69 canonical classes now load properly.

**And as a bonus, chronicle-based filtering.** The catalogue is now auto-adjusted to your CP's chronicle:

- CPs on C1, C2, C3, C4, C5, IL or Classic → **no Kamael** (they didn't exist yet).
- CPs on CT1, GF, HB or LU4 → **full catalogue** including Kamael.

Above the section you'll see an info pill: "Catalog filtered by your CP's X chronicle". If for any reason you don't belong to a CP, a banner explains it — remember the app expects every user to be in a CP.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Characters fix + chronicle-based class catalog'],
            [
                'audience' => 'web',
                'title_es' => 'Personajes arreglado + catálogo de clases por crónica',
                'title_en' => 'Characters fix + chronicle-based class catalog',
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
            ->where('title_en', 'Characters fix + chronicle-based class catalog')
            ->delete();
    }
};
