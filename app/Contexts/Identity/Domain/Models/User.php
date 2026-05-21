<?php

namespace App\Contexts\Identity\Domain\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\PointsLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'cp_id', 'role_id', 'membership_status', 'theme_preference', 'language_preference', 'changelog_last_seen_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'changelog_last_seen_at' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function pointsLogs()
    {
        return $this->hasMany(PointsLog::class);
    }

    public function getTotalPointsAttribute()
    {
        return $this->pointsLogs()->sum('points');
    }

    public function getTotalAdenaAttribute()
    {
        return $this->pointsLogs()->sum('adena');
    }
}
