<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    private ?array $pendingCustomAttributes = null;

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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProductVariant $variant) {
            if ($variant->pendingCustomAttributes === null) return;
            app(\App\Services\PimProductDataStore::class)
                ->replaceVariantAttributes($variant, $variant->pendingCustomAttributes);
            $variant->pendingCustomAttributes = null;
        });
    }

    /**
     * Parent main product relation.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributesRelation(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class)->orderBy('sort_order');
    }

    public function getCustomAttributesAttribute(): array
    {
        return $this->attributesRelation->map(fn (ProductVariantAttribute $item) => [
            'attributeCode' => $item->attribute_code,
            'value' => $item->value,
        ])->values()->all();
    }

    public function setCustomAttributesAttribute(?array $value): void
    {
        $this->pendingCustomAttributes = $value;
    }
}
