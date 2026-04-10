<?php

namespace App\Contexts\Identity\Domain\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Contexts\Party\Domain\Models\ConstParty;

#[Fillable(['name', 'email', 'password', 'cp_id', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
        return $this->hasMany(\App\Contexts\Party\Domain\Models\PointsLog::class);
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
