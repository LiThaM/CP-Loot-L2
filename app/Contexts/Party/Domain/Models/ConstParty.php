<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Models\LootReport;
use Illuminate\Database\Eloquent\Model;

class ConstParty extends Model
{
    // `leader_id` and `is_active` are NOT fillable: the first lets the CP
    // founder swap themselves out (privilege transfer), the second
    // suspends or deletes the CP. Both have legitimate code paths
    // (`RegisteredUserController` on first-member registration,
    // `ConstPartyController::toggleActive` for the admin) that use
    // forceFill() to bypass the guard after their own authorization.
    protected $fillable = ['name', 'server', 'chronicle', 'invite_code', 'logo_path', 'image_proof_required', 'tracker_enabled', 'tracker_divisor', 'tracker_enabled_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'image_proof_required' => 'boolean',
        'tracker_enabled' => 'boolean',
        'tracker_enabled_at' => 'datetime',
        'tracker_divisor' => 'integer',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute()
    {
        if ($this->logo_path) {
            return asset('storage/' . $this->logo_path);
        }
        
        // Return a premium looking default based on the CP name or just a fallback
        return null;
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members()
    {
        return $this->hasMany(User::class, 'cp_id')->where('membership_status', '!=', 'banned');
    }

    public function allMembers()
    {
        return $this->hasMany(User::class, 'cp_id');
    }

    public function pointsLogs()
    {
        return $this->hasMany(PointsLog::class, 'cp_id');
    }

    public function lootReports()
    {
        return $this->hasMany(LootReport::class, 'cp_id');
    }

    public function eventConfigs()
    {
        return $this->hasMany(CpEventConfig::class, 'cp_id');
    }

    public function rules()
    {
        return $this->hasOne(CpRule::class, 'cp_id');
    }
}
