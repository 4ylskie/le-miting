<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Allergen extends Model
{
    public const CODES = [
        'peanut', 'tree_nuts', 'milk', 'egg', 'soy', 'wheat_gluten', 'fish',
        'crustacean_shellfish', 'molluscs', 'sesame',
    ];

    protected $guarded = ['id'];

    public function assessments(): HasMany
    {
        return $this->hasMany(MenuItemAllergenAssessment::class);
    }
}
