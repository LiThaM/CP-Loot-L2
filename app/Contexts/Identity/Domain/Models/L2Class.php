<?php

namespace App\Contexts\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class L2Class extends Model
{
    protected $table = 'l2_classes';

    protected $fillable = ['code', 'name', 'race', 'class_type', 'parent_code'];

    public function scopeOfRace($query, string $race)
    {
        return $query->where('race', $race);
    }
}
