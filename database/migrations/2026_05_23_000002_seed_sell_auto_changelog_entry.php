<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Venta del CP Vault con reparto automático.** Si un mismo ítem está repartido entre varios farms (p.ej. 5 EAB = 1 + 1 + 3), el modal de venta ahora reparte la cantidad **automáticamente en orden FIFO** (farm más antiguo primero) y se crea **una venta por farm origen** en una sola acción.

- Cada venta paga a los attendees de **su** farm respetando el `cp_share_pct` de **ese** farm — no se mezcla la regla entre farms.
- El modal muestra el desglose previo a confirmar (`X uds del farm #Y · CP Z%` con la lista de attendees y su payout).
- Si quieres vender desde un único farm concreto (override manual), pulsa **"Elegir farm específico"** y vuelve al flujo clásico.
- Si un farm involucrado en el reparto no tiene attendees y su `cp_share_pct < 100`, se bloquea con un aviso pidiendo venderlo aparte con 100% al CP.
MD;

        $bodyEn = <<<'MD'
**CP Vault sale with auto allocation.** When the same item lives in multiple farms (e.g. 5 EAB = 1 + 1 + 3), the sell modal now splits the total **automatically in FIFO order** (oldest farm first) and creates **one sale per source farm** in a single action.

- Each sale pays the attendees of **its** farm using **that** farm's `cp_share_pct` — rules never mix between farms.
- The modal shows the breakdown before you confirm (`X units from farm #Y · CP Z%` plus the attendee list and payout).
- Need to liquidate just one specific farm? Hit **"Pick a specific farm"** to fall back to the single-source flow.
- If a farm involved in the allocation has no attendees and its `cp_share_pct < 100`, it's blocked with a warning; sell that one separately with CP 100%.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Auto FIFO sale across multiple source farms'],
            [
                'audience' => 'web',
                'title_es' => 'Venta automática FIFO entre varios farms origen',
                'title_en' => 'Auto FIFO sale across multiple source farms',
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
            ->where('title_en', 'Auto FIFO sale across multiple source farms')
            ->delete();
    }
};
