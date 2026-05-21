<?php

namespace App\Contexts\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    protected $table = 'characters';

    protected $fillable = ['user_id', 'name', 'l2_class_id', 'race', 'level'];

    protected $casts = [
        'level' => 'integer',
    ];

    protected static function booted(): void
    {
        // Keep `race` in sync with the chosen class so denormalised reads
        // (and the loot modal which highlights race) don't drift if the
        // user picks a class but forgets to refresh race.
        static::saving(function (Character $character) {
            if ($character->l2_class_id) {
                $class = L2Class::find($character->l2_class_id);
                if ($class) {
                    $character->race = $class->race;
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function l2Class()
    {
        return $this->belongsTo(L2Class::class, 'l2_class_id');
    }
}
