<?php

namespace App\Contexts\Loot\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Database\Eloquent\Model;

class LootReport extends Model
{
    protected $fillable = [
        'cp_id',
        'requested_by_id',
        'event_type',
        'points_per_member',
        'status',
        'image_proof',
        'description',
        'recipient_ids',
        'adena_distribution',
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
