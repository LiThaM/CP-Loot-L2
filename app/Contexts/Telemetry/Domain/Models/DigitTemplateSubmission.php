<?php

namespace App\Contexts\Telemetry\Domain\Models;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitTemplateSubmission extends Model
{
    protected $table = 'digit_template_submissions';

    public const ALLOWED_CHARS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'slash'];

    protected $fillable = [
        'anon_token_id',
        'char',
        'storage_path',
        'phash',
        'bot_version',
        'sharpness',
        'dim_w',
        'dim_h',
        'original_size_bytes',
        'kept_for_training',
        'submitted_at',
    ];

    protected $casts = [
        'sharpness' => 'float',
        'dim_w' => 'integer',
        'dim_h' => 'integer',
        'original_size_bytes' => 'integer',
        'kept_for_training' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }
}
