<?php

namespace App\Contexts\Loot\Domain\Models;

use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Database\Eloquent\Model;

class CpEventConfig extends Model
{
    protected $fillable = ['cp_id', 'event_type', 'points'];

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }
}
