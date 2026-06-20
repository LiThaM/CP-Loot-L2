<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ClanRaidBoss extends Model
{
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_ALIVE = 'alive';
    public const STATUS_KILLED = 'killed';

    protected $fillable = [
        'clan_id', 'name', 'level', 'respawn_hours', 'is_epic',
        'status', 'last_killed_at', 'window_opens_at', 'updated_by_user_id',
    ];

    protected $casts = [
        'level' => 'integer',
        'respawn_hours' => 'integer',
        'is_epic' => 'boolean',
        'last_killed_at' => 'datetime',
        'window_opens_at' => 'datetime',
    ];

    public function clan()
    {
        return $this->belongsTo(Clan::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function markKilled(?Carbon $killedAt = null): void
    {
        $at = $killedAt ?? now();
        $this->status = self::STATUS_KILLED;
        $this->last_killed_at = $at;
        $this->window_opens_at = $at->copy()->addHours($this->respawn_hours);
    }
}
