<?php

namespace App\Contexts\ClientApi\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ClientApiKey extends Model
{
    protected $table = 'client_api_keys';

    protected $fillable = [
        'key_hash',
        'label',
        'active',
        'version_range',
        'expires_at',
        'last_used_at',
        'use_count',
    ];

    protected $casts = [
        'active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'use_count' => 'integer',
    ];

    public static function hash(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    public static function findByRawKey(string $rawKey): ?self
    {
        return static::where('key_hash', static::hash($rawKey))->first();
    }

    public function isUsable(): bool
    {
        if (!$this->active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }
}
