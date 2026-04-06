<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Identity\Domain\Models\User;

class LootEntry extends Model
{
    protected $fillable = ['cp_id', 'item_id', 'requested_by', 'awarded_to', 'status', 'image_proof'];

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function awardedTo()
    {
        return $this->belongsTo(User::class, 'awarded_to');
    }
}
