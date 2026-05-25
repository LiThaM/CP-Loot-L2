<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Tres mejoras grandes en el flujo de loot/warehouse:

- **Marcar report como error (void).** Admin o cualquier `cp_leader` puede anular un report ya confirmado desde `/loot`. La fila queda visible con badge **ANULADO** y su efecto en stock y en adena owed se revierte instantáneamente. La acción es reversible (basta limpiar `voided_at` en BD).
- **Recheck de warehouse.** En el CP Vault, botón nuevo "🔍 Recheck items". Eliges items, ves el stock actual y escribes el real. El sistema crea solo los ajustes con delta ≠ 0, separados por ganancia / pérdida, sin tener que registrar manualmente N ADDs o N CONSUMEs.
- **Craft con confirmación clara.** Cuando un craft requiere auto-craftear intermedios, hay un modal preview ("se van a craftear 25× Crafted Leather, ¿aceptas o saltas?"). Si la receta tiene varios outputs (Foundation vs normal) o success rate < 100, un segundo modal pregunta el resultado real (positivo/negativo) y cuál output salió.

Plus quality of life: el badge pulsante de pending en `/loot` ya lo ven todos los líderes (no solo el founder); el link "Pending" del Dashboard aterriza directamente en su pestaña; el modal de aprobar deja confirmar cuando todos los attendees son externos; nuevo ajuste de CP "Captura obligatoria" para hacer opcional el screenshot en todos los formularios.
MD;

        $bodyEn = <<<'MD'
Three large improvements in the loot/warehouse flow:

- **Mark report as error (void).** Admin or any `cp_leader` can void a confirmed report from `/loot`. The row stays visible with a **VOIDED** badge and its effect on stock and adena owed is reverted instantly. Reversible (just clear `voided_at`).
- **Warehouse recheck.** New "🔍 Recheck items" button in the CP Vault. Pick items, see current stock, type real. The system only creates adjustments where delta ≠ 0, split into gain/loss reports — no need to manually register N ADDs or N CONSUMEs.
- **Craft with clearer confirmation.** When a craft needs to auto-craft intermediates, a preview modal shows "25× Crafted Leather will be auto-crafted, accept or skip?". When the recipe has multiple outputs (Foundation vs normal) or success rate < 100, a second modal asks the real outcome (positive/negative) and which output came out.

Plus quality of life: the pulsing pending badge in `/loot` is visible to every leader (not only the founder); the Dashboard "Pending" link lands on the right tab directly; the approve modal lets you confirm when all attendees are external; new CP setting "Screenshot required" to make image proof optional across every form.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Void reports, warehouse recheck, craft outcome modal'],
            [
                'audience' => 'web',
                'title_es' => 'Anular reports, recheck del warehouse, modal de resultado del craft',
                'title_en' => 'Void reports, warehouse recheck, craft outcome modal',
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
            ->where('title_en', 'Void reports, warehouse recheck, craft outcome modal')
            ->delete();
    }
};
