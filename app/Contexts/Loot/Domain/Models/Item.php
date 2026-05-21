<?php

namespace App\Contexts\Loot\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Item extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('visible', fn ($q) => $q->where($q->getModel()->getTable().'.hidden', false));
    }

    protected $casts = [
        'hidden' => 'boolean',
        'market_price' => 'integer',
        'market_price_updated_at' => 'datetime',
    ];

    protected $appends = ['market_price_updated_by_name'];


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
        'hidden', 'market_price', 'market_price_updated_at', 'market_price_updated_by',
    ];

    public function lootEntries()
    {
        return $this->hasMany(LootEntry::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function priceUpdatedBy()
    {
        return $this->belongsTo(User::class, 'market_price_updated_by');
    }

    public function getMarketPriceUpdatedByNameAttribute(): ?string
    {
        return $this->priceUpdatedBy?->name;
    }

    public function scopeLu4($query)
    {
        return $query->where(function ($q) {
            $q->where('chronicle', 'LU4')->orWhere('source', 'lu4_custom');
        });
    }
}
