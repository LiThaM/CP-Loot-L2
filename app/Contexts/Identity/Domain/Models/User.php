<?php

namespace App\Contexts\Identity\Domain\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\PointsLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'cp_id', 'role_id', 'membership_status', 'theme_preference', 'language_preference'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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
