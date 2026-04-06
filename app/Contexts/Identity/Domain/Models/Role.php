<?php

namespace App\Contexts\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'display_name', 'description'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
