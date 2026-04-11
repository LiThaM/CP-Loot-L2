<?php

namespace App\Contexts\Loot\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class LootEntry extends Model
{
    protected $fillable = ['loot_report_id', 'item_id', 'awarded_to', 'amount'];

    public function report()
    {
        return $this->belongsTo(LootReport::class, 'loot_report_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function awardedTo()
    {
        return $this->belongsTo(User::class, 'awarded_to');
    }
}
