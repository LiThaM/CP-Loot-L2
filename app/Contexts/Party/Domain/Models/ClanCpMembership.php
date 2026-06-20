<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClanCpMembership extends Model
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';

    protected $fillable = ['clan_id', 'cp_id', 'role', 'can_approve_attendance', 'joined_at'];

    protected $casts = [
        'can_approve_attendance' => 'boolean',
        'joined_at' => 'datetime',
    ];

    public function clan()
    {
        return $this->belongsTo(Clan::class);
    }

    public function constParty()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function canApprove(): bool
    {
        return $this->isAdmin() || $this->can_approve_attendance;
    }
}
