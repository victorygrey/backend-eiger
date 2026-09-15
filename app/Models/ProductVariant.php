<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'color',
        'size',
        'price',
        'stock',
        'image',
        'ecmsku',
        'moq',
        'custom_attributes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'custom_attributes' => 'array',
    ];

    /**
     * Parent main product relation.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
