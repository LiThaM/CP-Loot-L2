<?php

namespace App\Contexts\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use App\Contexts\Identity\Domain\Models\User;

class ConstParty extends Model
{
    protected $fillable = ['leader_id', 'name', 'server', 'chronicle', 'invite_code'];

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

    public function lootReports()
    {
        return $this->hasMany(\App\Contexts\Loot\Domain\Models\LootReport::class, 'cp_id');
    }

    public function eventConfigs()
    {
        return $this->hasMany(\App\Contexts\Loot\Domain\Models\CpEventConfig::class, 'cp_id');
    }
}
