<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item baseline price as exposed by the game itself ("NPC Sell Price"
 * on masterwork.wiki's LU4 pages). Independent column from
 * `market_price` — that one is wiki-style user-edited, the new one is
 * scraped from the game catalogue and read-only.
 *
 * UI can render `market_price` if present, fall back to
 * `npc_sell_price` otherwise (the "default" the user asked for).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'npc_sell_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedBigInteger('npc_sell_price')->nullable()->after('market_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'npc_sell_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('npc_sell_price');
            });
        }
    }
};
