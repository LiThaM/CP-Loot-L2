<?php

namespace App\Contexts\Loot\Domain\Models;

use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = ['cp_id', 'item_id', 'priority'];

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
