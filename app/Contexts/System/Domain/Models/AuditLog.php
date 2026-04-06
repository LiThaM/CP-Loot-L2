<?php

namespace App\Contexts\System\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use App\Contexts\Identity\Domain\Models\User;

class AuditLog extends Model
{
    protected $fillable = ['entity_type', 'entity_id', 'user_id', 'action', 'old_values', 'new_values'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
