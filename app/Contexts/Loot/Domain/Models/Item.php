<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['name', 'grade', 'category', 'image_url', 'base_points'];

    public function lootEntries()
    {
        return $this->hasMany(LootEntry::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }
}
