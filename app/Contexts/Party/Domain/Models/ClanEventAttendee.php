<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClanEventAttendee extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'clan_event_id', 'user_id', 'external_name', 'cp_id', 'status', 'approved_by_user_id',
    ];

    public function event()
    {
        return $this->belongsTo(ClanEvent::class, 'clan_event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function constParty()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
