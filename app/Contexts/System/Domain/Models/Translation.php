<?php

namespace App\Contexts\System\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = ['language', 'key', 'value'];
}
