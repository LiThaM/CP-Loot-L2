<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClanEvent extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_OPEN = 'open';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'clan_id', 'name', 'event_type', 'scheduled_at', 'occurred_at',
        'status', 'outcome', 'dkp_reward', 'notes',
    ];

    // created_by_user_id is set via forceFill() after authorization

    protected $casts = [
        'scheduled_at' => 'datetime',
        'occurred_at' => 'datetime',
        'dkp_reward' => 'integer',
    ];

    public function clan()
    {
        return $this->belongsTo(Clan::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function rsvps()
    {
        return $this->hasMany(ClanEventRsvp::class);
    }

    public function attendees()
    {
        return $this->hasMany(ClanEventAttendee::class);
    }

    public function approvedAttendees()
    {
        return $this->hasMany(ClanEventAttendee::class)->where('status', 'approved');
    }
}
