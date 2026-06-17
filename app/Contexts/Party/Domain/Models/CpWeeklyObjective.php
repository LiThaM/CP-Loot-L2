<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Loot\Domain\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpWeeklyObjective extends Model
{
    protected $fillable = [
        'cp_id',
        'item_id',
        'target_quantity',
        'multiplier',
        'completed_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'target_quantity' => 'integer',
        'multiplier' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function cp(): BelongsTo
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }
}
