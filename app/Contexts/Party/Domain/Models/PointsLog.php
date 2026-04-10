<?php

namespace App\Contexts\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use App\Contexts\Identity\Domain\Models\User;

class PointsLog extends Model
{
    protected $fillable = ['cp_id', 'user_id', 'action_type', 'points', 'adena', 'description'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }
}
