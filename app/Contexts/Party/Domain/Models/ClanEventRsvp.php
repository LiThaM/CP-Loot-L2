<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClanEventRsvp extends Model
{
    protected $fillable = ['clan_event_id', 'user_id', 'response'];

    public function event()
    {
        return $this->belongsTo(ClanEvent::class, 'clan_event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
