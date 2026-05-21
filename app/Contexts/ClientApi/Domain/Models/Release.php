<?php

namespace App\Contexts\ClientApi\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    protected $table = 'releases';

    protected $fillable = [
        'name',
        'version',
        'channel',
        'storage_path',
        'sha256',
        'size_bytes',
        'release_notes_md',
        'release_notes_es',
        'release_notes_en',
        'critical_update',
        'min_supported_version',
        'released_at',
        'published_at',
        'download_count',
    ];

    protected $casts = [
        'critical_update' => 'boolean',
        'released_at' => 'datetime',
        'published_at' => 'datetime',
        'size_bytes' => 'integer',
        'download_count' => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
