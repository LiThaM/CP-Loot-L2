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
        'cp_share_pct',
    ];

    protected $casts = [
        'recipient_ids' => 'array',
        'cp_share_pct' => 'integer',
    ];

    public function attendees()
    {
        return $this->hasMany(LootReportAttendee::class, 'loot_report_id');
    }

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    // Default newest-first. Use `entries()->reorder()->orderBy('id')` for
    // insertion order (e.g. FIFO consumption).
    public function entries()
    {
        return $this->hasMany(LootEntry::class, 'loot_report_id')->orderByDesc('id');
    }
}
