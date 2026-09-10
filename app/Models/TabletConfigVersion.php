<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabletConfigVersion extends Model
{
    protected $fillable = [
        'tablet_id',
        'version_number',
        'featured_product_id',
        'recommendation_product_ids',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'recommendation_product_ids' => 'array',
    ];

    public function tablet(): BelongsTo
    {
        return $this->belongsTo(Tablet::class);
    }

    public function featuredProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'featured_product_id');
    }
}
