<?php

namespace App\Contexts\ClientApi\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsMapdataVersion extends Model
{
    protected $table = 'gps_mapdata_versions';

    protected $fillable = [
        'anon_token_id',
        'storage_path',
        'sha256',
        'size_bytes',
        'source',
        'reverted_from_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }
}
