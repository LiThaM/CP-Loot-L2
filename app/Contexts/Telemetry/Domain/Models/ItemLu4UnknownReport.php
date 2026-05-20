<?php

namespace App\Contexts\Telemetry\Domain\Models;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemLu4UnknownReport extends Model
{
    protected $table = 'items_lu4_unknown_reports';

    public const STATUSES = ['pending', 'spam', 'promoted', 'rejected'];

    protected $fillable = [
        'name',
        'anon_token_id',
        'ocr_context',
        'count_seen',
        'status',
        'reported_at',
    ];

    protected $casts = [
        'count_seen' => 'integer',
        'reported_at' => 'datetime',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }
}
