<?php

namespace App\Contexts\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use App\Contexts\Identity\Domain\Models\User;

class ConstParty extends Model
{
    protected $fillable = ['leader_id', 'name', 'invite_code'];

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members()
    {
        return $this->hasMany(User::class, 'cp_id');
    }

    public function pointsLogs()
    {
        return $this->hasMany(PointsLog::class, 'cp_id');
    }
}
