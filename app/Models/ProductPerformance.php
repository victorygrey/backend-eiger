<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPerformance extends Model
{
    protected $fillable = [
        'product_id', 'pim_id', 'name', 'description', 'is_selected', 'selected_rating',
        'rating', 'rating_description', 'sort_order',
    ];

    protected $casts = [
        'is_selected' => 'boolean',
        'selected_rating' => 'decimal:2',
        'rating' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
