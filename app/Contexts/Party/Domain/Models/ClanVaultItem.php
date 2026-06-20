<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use Illuminate\Database\Eloquent\Model;

class ClanVaultItem extends Model
{
    public const STATUS_IN_VAULT = 'in_vault';
    public const STATUS_AUCTIONING = 'auctioning';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_RAFFLED = 'raffled';
    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'clan_id', 'item_id', 'item_name', 'item_image_url', 'quantity',
        'status', 'assigned_to_cp_id', 'deposited_by_user_id', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function clan()
    {
        return $this->belongsTo(Clan::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function assignedToCp()
    {
        return $this->belongsTo(ConstParty::class, 'assigned_to_cp_id');
    }

    public function depositedBy()
    {
        return $this->belongsTo(User::class, 'deposited_by_user_id');
    }

    public function auction()
    {
        return $this->hasOne(ClanVaultAuction::class, 'vault_item_id');
    }
}
