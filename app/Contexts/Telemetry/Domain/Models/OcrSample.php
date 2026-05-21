<?php

namespace App\Contexts\Telemetry\Domain\Models;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrSample extends Model
{
    protected $table = 'ocr_samples';

    public const CATEGORIES = ['bar', 'chat', 'chat_damage', 'system_msg', 'bar_misread', 'level'];
    public const STATUSES = ['pending', 'labeled', 'rejected', 'noisy'];

    protected $fillable = [
        'anon_token_id',
        'category',
        'storage_path',
        'image_hash_sha256',
        'ground_truth',
        'expected_value',
        'actual_ocr',
        'confidence',
        'bot_version',
        'status',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }
}
