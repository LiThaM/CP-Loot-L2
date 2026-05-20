<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
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
