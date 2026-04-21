<?php

namespace App\Contexts\Loot\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = [
        'external_id',
        'chronicle',
        'name',
        'recipe_item_id',
        'output_item_id',
        'output_quantity',
        'success_rate',
        'mp_cost',
        'adena_fee',
        'icon_name',
        'image_url',
        'scraper_url',
    ];

    public function outputItem()
    {
        return $this->belongsTo(Item::class, 'output_item_id');
    }
 
    public function recipeItem()
    {
        return $this->belongsTo(Item::class, 'recipe_item_id');
    }

    public function materials()
    {
        return $this->hasMany(RecipeMaterial::class);
    }

    public function outputs()
    {
        return $this->hasMany(RecipeOutput::class);
    }
}
