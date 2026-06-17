<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpDonation extends Model
{
    protected $fillable = [
        'cp_id',
        'user_id',
        'type',
        'item_id',
        'quantity',
        'adena_value',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'adena_value' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function cp(): BelongsTo
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }
}
