<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

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
        'description',
        'is_featured',
        'is_discontinued',
        'pim_media',
        'pim_payload',
        'pim_image_payload',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pim_media'       => 'array',
        'pim_payload' => 'array',
        'pim_image_payload' => 'array',
        'price'           => 'decimal:2',
        'stock'           => 'integer',
        'is_featured'     => 'boolean',
        'is_discontinued' => 'boolean',
    ];

    /**
     * Get product category accessor from pim_payload or zone.
     */
    public function getCategoryAttribute(): ?string
    {
        return $this->pim_payload['category']
            ?? $this->pim_payload['mc_name']
            ?? $this->zone?->name
            ?? null;
    }

    /**
     * Get product technologies from PIM payload.
     */
    public function getTechnologiesAttribute(): array
    {
        return $this->pim_payload['technology'] ?? [];
    }

    /**
     * Get product activities from PIM payload.
     */
    public function getActivitiesAttribute(): array
    {
        return $this->pim_payload['activity'] ?? [];
    }

    /**
     * Get product specifications from PIM payload.
     */
    public function getSpecificationsAttribute(): array
    {
        return $this->pim_payload['specification'] ?? [];
    }

    /**
     * Get product custom attributes from PIM payload.
     */
    public function getCustomAttributesListAttribute(): array
    {
        return $this->pim_payload['customAtributes'] ?? [];
    }

    /**
     * Get product weight from PIM payload.
     */
    public function getWeightAttribute(): ?int
    {
        return isset($this->pim_payload['weight']) ? (int) $this->pim_payload['weight'] : null;
    }

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
}
