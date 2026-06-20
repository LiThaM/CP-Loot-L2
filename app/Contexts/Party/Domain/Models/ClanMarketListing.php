<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use Illuminate\Database\Eloquent\Model;

class ClanMarketListing extends Model
{
    protected $fillable = [
        'clan_id', 'user_id', 'listing_type', 'item_type',
        'item_id', 'item_name', 'item_image_url', 'quantity',
        'price', 'is_negotiable', 'contact_info', 'status', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'integer',
        'is_negotiable' => 'boolean',
    ];

    public function clan()
    {
        return $this->belongsTo(Clan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
