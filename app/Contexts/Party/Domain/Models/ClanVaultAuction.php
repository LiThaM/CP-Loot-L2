<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClanVaultAuction extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'vault_item_id', 'min_bid', 'ends_at', 'status',
        'winner_user_id', 'winning_bid', 'closed_by_user_id',
    ];

    protected $casts = [
        'min_bid' => 'integer',
        'ends_at' => 'datetime',
        'winning_bid' => 'integer',
    ];

    public function vaultItem()
    {
        return $this->belongsTo(ClanVaultItem::class, 'vault_item_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function bids()
    {
        return $this->hasMany(ClanVaultAuctionBid::class, 'auction_id')->orderByDesc('bid_amount');
    }
}
