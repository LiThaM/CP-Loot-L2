<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class Clan extends Model
{
    protected $fillable = ['name', 'description', 'logo_path', 'invite_code', 'is_active'];

    // created_by_user_id is not fillable — set via forceFill() after the ownership
    // of the action has been verified, preventing accidental creator reassignment.

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : null;
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberships()
    {
        return $this->hasMany(ClanCpMembership::class);
    }

    public function constParties()
    {
        return $this->hasManyThrough(
            ConstParty::class,
            ClanCpMembership::class,
            'clan_id',
            'id',
            'id',
            'cp_id'
        );
    }

    public function events()
    {
        return $this->hasMany(ClanEvent::class);
    }

    public function raidBosses()
    {
        return $this->hasMany(ClanRaidBoss::class);
    }

    public function vaultItems()
    {
        return $this->hasMany(ClanVaultItem::class);
    }

    public function marketListings()
    {
        return $this->hasMany(ClanMarketListing::class);
    }

    public function dkpAdjustments()
    {
        return $this->hasMany(ClanDkpAdjustment::class);
    }

    public function ownerMembership()
    {
        return $this->hasOne(ClanCpMembership::class)->where('role', 'owner');
    }
}
