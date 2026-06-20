<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClanDkpAdjustment extends Model
{
    protected $fillable = ['clan_id', 'user_id', 'amount', 'reason', 'adjusted_by_user_id'];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function clan()
    {
        return $this->belongsTo(Clan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by_user_id');
    }
}
