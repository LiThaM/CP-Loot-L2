<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Models\LootReport;
use Illuminate\Database\Eloquent\Model;

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
        return $this->hasMany(LootReport::class, 'cp_id');
    }

    public function eventConfigs()
    {
        return $this->hasMany(CpEventConfig::class, 'cp_id');
    }
}
