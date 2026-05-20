<?php

namespace App\Contexts\System\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ChangelogEntry extends Model
{
    public const AUDIENCES = ['web', 'bot', 'both'];

    protected $fillable = [
        'type',
        'version',
        'audience',
        'release_id',
        'title_es',
        'title_en',
        'body_es',
        'body_en',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function scopeForAudience($query, string $audience)
    {
        return $query->whereIn('audience', [$audience, 'both']);
    }
}
