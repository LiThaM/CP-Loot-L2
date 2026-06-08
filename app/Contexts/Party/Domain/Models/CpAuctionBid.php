<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class CpAuctionBid extends Model
{
    protected $fillable = ['auction_id', 'user_id', 'amount', 'placed_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public function auction() { return $this->belongsTo(CpAuction::class, 'auction_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
