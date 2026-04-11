<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeOutput extends Model
{
    protected $fillable = [
        'recipe_id',
        'item_id',
        'quantity',
        'chance',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
