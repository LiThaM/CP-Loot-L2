<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeMaterial extends Model
{
    protected $fillable = [
        'recipe_id',
        'item_id',
        'quantity',
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
