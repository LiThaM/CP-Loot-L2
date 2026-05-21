<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Nueva pantalla `/party/craft-bulk`:**

- Añade varias recetas con cantidad en una sola sesión.
- El sistema suma materiales, cruza contra tu Warehouse CP y te dice qué tienes y qué te falta.
- Si te faltan materiales intermedios craftables (ej. Crafted Leather), el sistema decide cuántos sub-craftear y desglosa SOLO esos. Si tienes 10 y necesitas 12, sólo te pide hacer 2.
- Read-only por ahora: sólo calcula, no consume warehouse ni crea reportes. Útil como herramienta de planificación.
- Acceso para líder fundador, co-líderes y contables.
MD;

        $bodyEn = <<<'MD'
**New `/party/craft-bulk` screen:**

- Add multiple recipes with quantities in one go.
- The system aggregates materials, crosses them against your CP warehouse and tells you what you have and what's missing.
- Missing intermediate craftables (e.g. Crafted Leather) are sub-crafted automatically: if you have 10 and need 12, it only asks you to craft 2.
- Read-only for now: it only calculates — does not touch the warehouse or create reports. Useful as a planning tool.
- Available to founder, co-leaders and accountants.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Party: bulk crafting calculator'],
            [
                'audience' => 'web',
                'title_es' => 'Party: calculadora de craft masivo',
                'title_en' => 'Party: bulk crafting calculator',
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
            ->where('title_en', 'Party: bulk crafting calculator')
            ->delete();
    }
};
