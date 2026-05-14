<?php

namespace App\Contexts\System\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ChangelogEntry extends Model
{
    protected $fillable = [
        'type',
        'version',
        'title_es',
        'title_en',
        'body_es',
        'body_en',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];
}
