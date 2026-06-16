<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Warehouse: tracker points per item'],
            [
                'audience' => 'web',
                'title_es' => 'Almacén: puntos del tracker por item',
                'title_en' => 'Warehouse: tracker points per item',
                'body_es' => <<<'MD'
En el almacén, junto al precio de mercado, ahora ves los **puntos por unidad** que vale cada item según vuestro tracker de valor: precio efectivo (mercado, con venta NPC de respaldo) ÷ divisor, con el mismo redondeo que tenéis configurado (entero al alza si usáis "puntos enteros", si no con decimales). Pasa el ratón por encima para el detalle. Solo aparece si tu CP tiene el tracker activado.
MD,
                'body_en' => <<<'MD'
In the warehouse, next to the market price, you now see the **points per unit** each item is worth according to your value tracker: effective price (market, with NPC sell as fallback) ÷ divisor, using the same rounding you've configured (whole numbers if "whole points" is on, otherwise decimals). Hover for details. Only shown if your CP has the tracker enabled.
MD,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_en', 'Warehouse: tracker points per item')
            ->delete();
    }
};
