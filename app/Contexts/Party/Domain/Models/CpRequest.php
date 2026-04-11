<?php

namespace App\Contexts\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CpRequest extends Model
{
    protected $table = 'cp_requests';

    protected $fillable = [
        'cp_name',
        'server',
        'chronicle',
        'leader_name',
        'contact_email',
        'message',
        'status',
        'approved_at',
        'approved_by_user_id',
    ];
}
