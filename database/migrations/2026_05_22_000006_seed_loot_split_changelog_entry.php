<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Nuevo en el reparto de loot:**

- **Asistentes externos**: ahora puedes añadir gente que farmeó contigo y NO está en tu CP por su nick. Se marcan con badge "externo" y el sistema lleva la cuenta de cuánto les debes.
- **Slider de porcentaje al fondo de la CP**: en el modal de reportar loot y al vender, eliges qué % se queda en el fondo de la CP y el resto se reparte entre asistentes. Presets 0/10/20/50/100 % o número libre.
- **Sesión de farm origen explícita al vender**: el modal de venta ahora obliga a elegir qué sesión concreta de farm estás liquidando — adiós a la heurística "última farm con este item" que pagaba al grupo equivocado.
- **Pagos externos**: nueva pantalla `Pagos externos` para que los líderes vean a qué externos hay que pagar y marcarlos como liquidados. Los miembros también pueden consultarla en modo solo lectura, para transparencia.
- **Sin redondeo perdido**: el resto del split por división entera ahora va al fondo de la CP en vez de evaporarse.

**Bug fixes:**

- Vender un item ya no se "come" la última farm de la lista por defecto.
- El % al fondo de la CP funciona como esperas — antes era binario `cp|attendees`.
MD;

        $bodyEn = <<<'MD'
**New in loot splitting:**

- **External attendees**: you can now add people who farmed with you but are NOT in your CP by their nick. They show up with an "external" badge and the system tracks what you owe them.
- **CP-fund percentage slider**: in both the report-loot modal and the sell modal you pick the % that stays in the CP fund, the rest splits among attendees. Presets 0/10/20/50/100 or free entry.
- **Explicit source farm session on sell**: the sell modal now requires you to pick which exact farm session you're liquidating — no more "last farm with this item" heuristic paying the wrong group.
- **External payouts page**: new `External payouts` screen so CP leaders can see whom they still owe and mark paid. Members can read it too for transparency.
- **No more lost rounding**: the integer-division remainder now lands in the CP fund instead of evaporating.

**Bug fixes:**

- Selling an item no longer auto-picks the wrong farm to charge against.
- The CP-fund percentage now behaves as you'd expect — it used to be a binary cp/attendees toggle.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Loot split rework: external attendees & CP-fund percentage'],
            [
                'audience' => 'web',
                'title_es' => 'Reparto de loot: asistentes externos y % al fondo de la CP',
                'title_en' => 'Loot split rework: external attendees & CP-fund percentage',
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
            ->where('title_en', 'Loot split rework: external attendees & CP-fund percentage')
            ->delete();
    }
};
