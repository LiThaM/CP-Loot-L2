<?php

namespace App\Contexts\Telemetry\Domain\Models;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalibrationFailure extends Model
{
    protected $table = 'calibration_failures';

    protected $fillable = [
        'anon_token_id',
        'kind',
        'char_name',
        'app_version',
        'meta_json',
        'image_path',
        'image_bytes',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'image_bytes' => 'integer',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }
}
