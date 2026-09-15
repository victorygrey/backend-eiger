<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FitAndGoActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'care_mc_level_2',
        'image',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function recommendedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'fit_and_go_activity_products', 'activity_id', 'product_id')
            ->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }
}
