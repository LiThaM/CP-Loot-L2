<?php

namespace App\Contexts\ClientApi\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AnonToken extends Model
{
    protected $table = 'anon_tokens';

    protected $fillable = [
        'token_uuid',
        'first_seen_at',
        'last_seen_at',
        'request_count',
        'country_code_last',
        'banned_at',
        'banned_reason',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'banned_at' => 'datetime',
        'request_count' => 'integer',
    ];

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function hashedToken(): string
    {
        return hash('sha256', $this->token_uuid);
    }
}
