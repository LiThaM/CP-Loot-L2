<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Identity\Domain\Models\User;

class LootReport extends Model
{
    protected $fillable = [
        'cp_id', 
        'requested_by_id', 
        'event_type', 
        'points_per_member', 
        'status', 
        'image_proof', 
        'recipient_ids'
    ];

    protected $casts = [
        'recipient_ids' => 'array',
    ];

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function entries()
    {
        return $this->hasMany(LootEntry::class, 'loot_report_id');
    }
}
