<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The reference wiki we scrape lists the price the NPC SELLS an item at,
     * but a player selling it back to a store only gets ~half. Bring the
     * stored `npc_sell_price` down to that real sell-back value (÷2, floored)
     * for LU4 so the "base price" matches what players actually receive.
     * Only LU4 has npc_sell_price populated; other chronicles are untouched.
     * Manually-set market prices are NOT affected (different column).
     */
    public function up(): void
    {
        DB::table('items')
            ->where('chronicle', 'LU4')
            ->whereNotNull('npc_sell_price')
            ->update(['npc_sell_price' => DB::raw('FLOOR(npc_sell_price / 2)')]);
    }

    public function down(): void
    {
        // Best-effort reverse — the floored odd remainder can't be recovered.
        DB::table('items')
            ->where('chronicle', 'LU4')
            ->whereNotNull('npc_sell_price')
            ->update(['npc_sell_price' => DB::raw('npc_sell_price * 2')]);
    }
};
