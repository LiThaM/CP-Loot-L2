<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'hidden')) {
            Schema::table('items', function (Blueprint $table) {
                $table->boolean('hidden')->default(false)->after('category');
                $table->index('hidden');
            });
        }

        // Pre-existing placeholder rows ("(Not In Use)", "(Not Use)") were
        // historically filtered by name-matching in every controller. Move
        // them under the same hidden flag so the global scope handles it.
        DB::table('items')
            ->whereRaw('LOWER(name) LIKE ?', ['%not in use%'])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%not use%'])
            ->update(['hidden' => true]);

        // Resolve duplicates: same (name, chronicle, grade) imported as
        // both a typed row (Recipe/Weapon/Armor/...) and a generic EtcItem.
        // Keep the typed one (or lowest id when tied) as canonical, repoint
        // every reference, hide the rest.
        $groups = DB::table('items')
            ->select('name', 'chronicle', 'grade')
            ->where('hidden', false)
            ->groupBy('name', 'chronicle', 'grade')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $g) {
            $rows = DB::table('items')
                ->where('name', $g->name)
                ->where('chronicle', $g->chronicle)
                ->where('grade', $g->grade)
                ->where('hidden', false)
                ->orderByRaw("CASE WHEN category = 'EtcItem' THEN 1 ELSE 0 END")
                ->orderBy('id')
                ->get();

            $canon = $rows->shift();
            $hideIds = $rows->pluck('id')->all();
            if (! $hideIds) {
                continue;
            }

            DB::table('loot_entries')->whereIn('item_id', $hideIds)->update(['item_id' => $canon->id]);
            DB::table('recipe_materials')->whereIn('item_id', $hideIds)->update(['item_id' => $canon->id]);
            DB::table('recipe_outputs')->whereIn('item_id', $hideIds)->update(['item_id' => $canon->id]);
            DB::table('recipes')->whereIn('output_item_id', $hideIds)->update(['output_item_id' => $canon->id]);
            DB::table('recipes')->whereIn('recipe_item_id', $hideIds)->update(['recipe_item_id' => $canon->id]);
            DB::table('wishlists')->whereIn('item_id', $hideIds)->update(['item_id' => $canon->id]);

            DB::table('items')->whereIn('id', $hideIds)->update(['hidden' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'hidden')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropIndex(['hidden']);
                $table->dropColumn('hidden');
            });
        }
    }
};
