<?php

namespace App\Contexts\ClientApi\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSession extends Model
{
    protected $table = 'game_sessions';

    protected $fillable = [
        'anon_token_id',
        'char_name',
        'app_version',
        'started_at',
        'ended_at',
        'xp',
        'sp',
        'adena',
        'mobs_killed',
        'deaths',
        'level_ups',
        'xp_per_hour',
        'adena_per_hour',
        'items_summary_json',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'xp' => 'integer',
        'sp' => 'integer',
        'adena' => 'integer',
        'mobs_killed' => 'integer',
        'deaths' => 'integer',
        'level_ups' => 'integer',
        'xp_per_hour' => 'integer',
        'adena_per_hour' => 'integer',
        'items_summary_json' => 'array',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }

    public function durationSeconds(): int
    {
        return max(0, (int) $this->started_at->diffInSeconds($this->ended_at));
    }
}
