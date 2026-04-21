<?php

namespace App\Contexts\System\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'path',
        'method',
        'ip',
        'user_agent',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
