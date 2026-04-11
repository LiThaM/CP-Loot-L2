<?php

namespace App\Contexts\Loot\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Database\Eloquent\Model;

class CpRecipe extends Model
{
    protected $table = 'cp_recipes';

    protected $fillable = [
        'cp_id',
        'recipe_id',
        'priority',
        'created_by',
    ];

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
