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

    // `role_id` is intentionally NOT fillable — a mass-assignment of that
    // column would let any authenticated user promote themselves to admin
    // via `User::create($request->all())` or `->update($request->only(...))`.
    // The only legitimate code paths that change a user's role
    // (`UserManagementController::updateRole`) use forceFill() explicitly.
    protected $fillable = ['name', 'email', 'password', 'cp_id', 'membership_status', 'theme_preference', 'language_preference', 'changelog_last_seen_at', 'main_class_id', 'main_race', 'main_level'];

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

    public function characters()
    {
        return $this->hasMany(Character::class);
    }

    public function mainClass()
    {
        return $this->belongsTo(L2Class::class, 'main_class_id');
    }
}
