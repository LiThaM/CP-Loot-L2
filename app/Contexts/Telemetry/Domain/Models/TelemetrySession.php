<?php

namespace App\Contexts\Telemetry\Domain\Models;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetrySession extends Model
{
    protected $table = 'telemetry_sessions';

    protected $fillable = [
        'anon_token_id',
        'country_code',
        'bot_version',
        'os_version',
        'python_version',
        'session_duration_seconds',
        'char_class',
        'char_level',
        'xp_per_hour',
        'adena_per_hour',
        'ss_per_hour',
        'deaths',
        'level_ups',
        'top_items_json',
        'ocr_engine',
        'ocr_avg_ms',
        'ocr_p95_ms',
        'ocr_errors',
        'ocr_gpu_used',
    ];

    protected $casts = [
        'top_items_json' => 'array',
        'session_duration_seconds' => 'integer',
        'char_level' => 'integer',
        'xp_per_hour' => 'integer',
        'adena_per_hour' => 'integer',
        'ss_per_hour' => 'integer',
        'deaths' => 'integer',
        'level_ups' => 'integer',
        'ocr_avg_ms' => 'float',
        'ocr_p95_ms' => 'float',
        'ocr_errors' => 'integer',
        'ocr_gpu_used' => 'boolean',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }
}
