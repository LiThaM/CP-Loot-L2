<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Item extends Model
{
    /**
     * Maps `item_id → recipe_id` for every craftable item in a chronicle.
     * Used by the recipe explorer and the bulk-craft planner to decide
     * whether a node can be expanded. Picks the lowest recipe_id when
     * several recipes produce the same item.
     *
     * @return array<int,int>
     */
    public static function craftableRecipeIdByItemId(string $chronicle): array
    {
        $direct = DB::table('recipes')
            ->where('chronicle', $chronicle)
            ->whereNotNull('output_item_id')
            ->get(['id', 'output_item_id'])
            ->map(fn ($r) => ['item_id' => (int) $r->output_item_id, 'recipe_id' => (int) $r->id]);

        $alt = DB::table('recipe_outputs')
            ->join('recipes', 'recipes.id', '=', 'recipe_outputs.recipe_id')
            ->where('recipes.chronicle', $chronicle)
            ->get(['recipe_outputs.item_id', 'recipe_outputs.recipe_id'])
            ->map(fn ($r) => ['item_id' => (int) $r->item_id, 'recipe_id' => (int) $r->recipe_id]);

        $map = [];
        foreach ($direct->concat($alt)->groupBy('item_id') as $itemId => $rows) {
            $map[(int) $itemId] = (int) $rows->sortBy('recipe_id')->first()['recipe_id'];
        }
        return $map;
    }

    protected $fillable = [
        'name', 'grade', 'category', 'image_url', 'base_points',
        'external_id', 'chronicle', 'source', 'icon_name', 'description',
    ];

    public function lootEntries()
    {
        return $this->hasMany(LootEntry::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function scopeLu4($query)
    {
        return $query->where(function ($q) {
            $q->where('chronicle', 'LU4')->orWhere('source', 'lu4_custom');
        });
    }
}
