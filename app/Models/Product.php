<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    private ?array $pendingPimPayload = null;
    private ?array $pendingPimImagePayload = null;
    private ?array $pendingPimMedia = null;
    private ?string $pendingPimVersion = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sku',
        'name',
        'price',
        'stock',
        'zone_id',
        'image',
        'material',
        'category',
        'gender',
        'product_group',
        'weight',
        'description',
        'is_featured',
        'is_discontinued',
        'pim_catalog_active',
        'pim_synced_at',
        'care_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pim_catalog_active' => 'boolean',
        'weight'          => 'decimal:3',
        'pim_synced_at'   => 'datetime',
        'care_synced_at'  => 'datetime',
        'price'           => 'decimal:2',
        'stock'           => 'integer',
        'is_featured'     => 'boolean',
        'is_discontinued' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (Product $product) {
            if ($product->pendingPimPayload === null && $product->pendingPimImagePayload === null
                && $product->pendingPimMedia === null && $product->pendingPimVersion === null) return;

            $payload = $product->pendingPimPayload ?? $product->pim_payload ?? [
                'generic' => $product->sku, 'name' => $product->name, 'variant' => [],
                'customAtributes' => [], 'media' => [], 'technology' => [], 'activity' => [], 'specification' => [],
            ];
            app(\App\Services\PimProductDataStore::class)->replace(
                $product,
                $payload,
                $product->pendingPimImagePayload ?? $product->pim_image_payload ?? [],
                $product->pendingPimMedia ?? $product->pim_media ?? [],
                $product->pendingPimVersion ?? $product->pim_version,
                'model-compatibility',
            );
            $product->pendingPimPayload = $product->pendingPimImagePayload = $product->pendingPimMedia = null;
            $product->pendingPimVersion = null;
        });
    }

    /**
     * Get product category accessor from pim_payload or zone.
     */
    public function getCategoryAttribute(?string $value): ?string
    {
        return $value
            ?? $this->pim_payload['category']
            ?? $this->pim_payload['mc_name']
            ?? $this->zone?->name
            ?? null;
    }

    /**
     * Get product technologies from PIM payload.
     */
    public function getTechnologiesAttribute(): array
    {
        return $this->technologiesRelation->map(fn (ProductTechnology $item) => array_filter([
            'id' => $item->pim_id, 'name' => $item->name, 'description' => $item->description,
            'image' => $item->image_url,
        ], fn ($value) => $value !== null))->values()->all();
    }

    /**
     * Get product activities from PIM payload.
     */
    public function getActivitiesAttribute(): array
    {
        return $this->activitiesRelation->map(fn (ProductActivity $item) => array_filter([
            'id' => $item->pim_id, 'name' => $item->name, 'description' => $item->description,
            'selected' => $item->is_selected, 'rating' => $item->rating,
            'desc_rating' => $item->rating_description,
        ], fn ($value) => $value !== null))->values()->all();
    }

    /**
     * Get product specifications from PIM payload.
     */
    public function getSpecificationsAttribute(): array
    {
        return $this->specificationsRelation->map(fn (ProductSpecification $item) => array_filter([
            'code' => $item->code, 'name' => $item->name, 'value' => $item->value, 'unit' => $item->unit,
        ], fn ($value) => $value !== null))->values()->all();
    }

    /**
     * Get product custom attributes from PIM payload.
     */
    public function getCustomAttributesListAttribute(): array
    {
        return $this->customAttributesRelation->map(fn (ProductCustomAttribute $item) => [
            'attributeCode' => $item->attribute_code, 'value' => $item->value,
        ])->values()->all();
    }

    /**
     * Get product weight from PIM payload.
     */
    public function getWeightAttribute(mixed $value): float|int|null
    {
        if ($value !== null) return (float) $value;
        return isset($this->pim_payload['weight']) ? (float) $this->pim_payload['weight'] : null;
    }

    public function getPimPayloadAttribute(): ?array
    {
        return $this->pimRecord?->product_payload;
    }

    public function setPimPayloadAttribute(?array $value): void { $this->pendingPimPayload = $value; }

    public function getPimImagePayloadAttribute(): ?array
    {
        return $this->pimRecord?->image_payload;
    }

    public function setPimImagePayloadAttribute(?array $value): void { $this->pendingPimImagePayload = $value; }

    public function getPimVersionAttribute(): ?string
    {
        return $this->pimRecord?->schema_version;
    }

    public function setPimVersionAttribute(?string $value): void { $this->pendingPimVersion = $value; }

    public function getPimMediaAttribute(): array
    {
        return $this->mediaRelation->map(fn (ProductMedia $item) => array_filter([
            'id' => $item->external_id, 'url' => $item->url, 'role' => $item->role,
            'type' => $item->media_type, 'attributeCode' => $item->attribute_code,
            'source_url' => $item->source_url, 'source' => $item->source,
            'description' => $item->description, 'sku' => $item->sku,
            'sha256' => $item->checksum, 'mime' => $item->mime_type,
        ], fn ($value) => $value !== null))->values()->all();
    }

    public function setPimMediaAttribute(?array $value): void { $this->pendingPimMedia = $value; }

    /**
     * Get image URL accessor.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image;
    }

    /**
     * Get the zone this product belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the RFID tag associated with this product.
     */
    public function rfidTag(): HasOne
    {
        return $this->hasOne(RfidTag::class);
    }

    public function recommendedOnTablets(): BelongsToMany
    {
        return $this->belongsToMany(Tablet::class, 'tablet_recommendations')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Get all variants for this product.
     */
    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function pimRecord(): HasOne { return $this->hasOne(ProductPimRecord::class); }
    public function customAttributesRelation(): HasMany { return $this->hasMany(ProductCustomAttribute::class)->orderBy('sort_order'); }
    public function technologiesRelation(): HasMany { return $this->hasMany(ProductTechnology::class)->orderBy('sort_order'); }
    public function activitiesRelation(): HasMany { return $this->hasMany(ProductActivity::class)->orderBy('sort_order'); }
    public function specificationsRelation(): HasMany { return $this->hasMany(ProductSpecification::class)->orderBy('sort_order'); }
    public function mediaRelation(): HasMany { return $this->hasMany(ProductMedia::class)->orderBy('sort_order'); }
}
