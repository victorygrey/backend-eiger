<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pim_media'       => 'array',
        'price'           => 'decimal:2',
        'stock'           => 'integer',
        'is_featured'     => 'boolean',
        'is_discontinued' => 'boolean',
    ];

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
}
