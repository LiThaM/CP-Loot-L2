<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClanVaultAuctionBid extends Model
{
    protected $fillable = ['auction_id', 'user_id', 'bid_amount'];

    protected $casts = [
        'bid_amount' => 'integer',
    ];

    public function auction()
    {
        return $this->belongsTo(ClanVaultAuction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
