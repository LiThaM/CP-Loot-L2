<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class CpRule extends Model
{
    protected $table = 'cp_rules';

    protected $fillable = ['cp_id', 'version', 'body', 'updated_by_id'];

    protected $casts = [
        'version' => 'integer',
    ];

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
