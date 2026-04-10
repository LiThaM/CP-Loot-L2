<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use App\Contexts\Party\Domain\Models\ConstParty;

class CpEventConfig extends Model
{
    protected $fillable = ['cp_id', 'event_type', 'points'];

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }
}
