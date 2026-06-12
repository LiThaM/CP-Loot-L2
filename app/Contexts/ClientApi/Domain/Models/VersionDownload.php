<?php

namespace App\Contexts\ClientApi\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionDownload extends Model
{
    protected $table = 'version_downloads';

    protected $fillable = [
        'anon_token_id',
        'from_version',
        'to_version',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }
}
