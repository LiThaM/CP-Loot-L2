<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $entries = [
            [
                'title_en' => 'Warehouse market prices + craft cost',
                'title_es' => 'Precios de mercado del almacén + coste de crafteo',
                'body_es' => <<<'MD'
**Precios de mercado para LU4.** Cargamos los precios de mercado de los materiales (los podéis seguir ajustando a mano).

**Coste de crafteo automático.** Cada objeto crafteable muestra, junto a su precio (con un icono de herramienta), un segundo precio calculado a partir del precio de mercado de los materiales que necesita su receta — recursivo, así un Artisan's Frame se calcula desde sus moldes, y estos desde sus minerales. Lo veréis en el almacén y en el explorador de recetas.

**Solo oficiales fijan el precio.** Ahora únicamente admin, líder y contable pueden poner el precio de mercado de un objeto. Si un miembro lo intenta, recibe un aviso de que no tiene el rol.
MD,
                'body_en' => <<<'MD'
**Market prices for LU4.** We loaded market prices for materials (you can keep adjusting them by hand).

**Automatic craft cost.** Every craftable item shows, next to its price (with a tool icon), a second price derived from the market price of the materials its recipe needs — recursive, so an Artisan's Frame is priced from its molds, and those from their ores. You'll see it in the warehouse and in the recipe explorer.

**Only officers set prices.** Only admin, leader and accountant can set an item's market price now. If a member tries, they get a notice that they don't have the role.
MD,
            ],
            [
                'title_en' => 'Value tracker: new scoring options',
                'title_es' => 'Tracker de valor: nuevas opciones de cálculo',
                'body_es' => <<<'MD'
El tracker de valor (DKP) gana ajustes por CP, en **Ajustes → Tracker**:

- **Valuar por precio de mercado** (o por precio de venta al NPC como alternativa).
- **Redondear arriba los drops por debajo de 1000 adena** (los drops baratos no valen fracciones).
- **Puntos enteros** (sin decimales, redondeando siempre al alza).
- Botón **Recalcular tracker** para reconstruir los puntos con los precios y ajustes actuales — las contribuciones manuales se conservan.
MD,
                'body_en' => <<<'MD'
The value tracker (DKP) gets per-CP settings, under **Settings → Tracker**:

- **Value by market price** (or by NPC sell price as the alternative).
- **Round up drops under 1000 adena** (cheap drops aren't worth fractions).
- **Whole points** (no decimals, always rounding up).
- A **Recompute tracker** button to rebuild points with the current prices and settings — manual contributions are kept.
MD,
            ],
            [
                'title_en' => 'Clearer loot approval',
                'title_es' => 'Aprobar loot más claro',
                'body_es' => <<<'MD'
El modal de **resolver sesión de loot** se lee mejor: nombres de objetos completos (con tooltip) y lista con su propio scroll, para que una sesión con muchos objetos no entierre el botón de confirmar. Además, si el envío falla ahora verás el motivo (toast) en vez de quedarte sin respuesta. Y **aprobar una sesión con un invitado (asistente externo) ya funciona** — antes se perdían los miembros internos y la sesión se quedaba pendiente. Los invitados reciben su parte de **adena** (se liquida en Pagos Externos), pero no puntos.
MD,
                'body_en' => <<<'MD'
The **resolve loot session** modal reads better: full item names (with tooltip) and a scrollable list, so a session with many items no longer buries the confirm button. Also, if submitting fails you now see why (toast) instead of nothing happening. And **approving a session that includes a guest (external attendee) now works** — it used to drop the internal members and leave the session pending. Guests get their **adena** share (settled in External Payouts), but no points.
MD,
            ],
        ];

        foreach ($entries as $e) {
            DB::table('changelog_entries')->updateOrInsert(
                ['type' => 'feature', 'version' => null, 'title_en' => $e['title_en']],
                [
                    'audience' => 'web',
                    'title_es' => $e['title_es'],
                    'title_en' => $e['title_en'],
                    'body_es' => $e['body_es'],
                    'body_en' => $e['body_en'],
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('changelog_entries')->whereIn('title_en', [
            'Warehouse market prices + craft cost',
            'Value tracker: new scoring options',
            'Clearer loot approval',
        ])->delete();
    }
};
