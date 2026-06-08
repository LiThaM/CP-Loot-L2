<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootReport;
use Illuminate\Database\Eloquent\Model;

/**
 * A CP auction: the leader puts an item from the warehouse up for grabs,
 * members bid (DKP points or adena), the cron closes at `ends_at` and
 * the leader manually fulfills to hand over the item and charge the
 * winner. The item is "reserved" from the warehouse at open() time
 * via a WAREHOUSE_RECHECK_LOSS report and only released (back to stock
 * via WAREHOUSE_RECHECK_GAIN) if the auction is cancelled.
 */
class CpAuction extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    public const CURRENCY_POINTS = 'points';
    public const CURRENCY_ADENA = 'adena';

    protected $fillable = [
        'cp_id', 'item_id', 'amount',
        'currency', 'starting_bid', 'buy_now_price',
        'current_bid', 'current_bidder_id',
        'ends_at', 'status',
        'winner_id', 'fulfilled_at',
        'created_by_user_id',
        'reservation_report_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'starting_bid' => 'decimal:2',
        'buy_now_price' => 'decimal:2',
        'current_bid' => 'decimal:2',
        'ends_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function cp() { return $this->belongsTo(ConstParty::class, 'cp_id'); }
    public function item() { return $this->belongsTo(Item::class); }
    public function currentBidder() { return $this->belongsTo(User::class, 'current_bidder_id'); }
    public function winner() { return $this->belongsTo(User::class, 'winner_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function reservationReport() { return $this->belongsTo(LootReport::class, 'reservation_report_id'); }
    public function bids() { return $this->hasMany(CpAuctionBid::class, 'auction_id')->orderByDesc('placed_at'); }
}
