<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tablet extends Model
{
    use HasFactory;

    protected $attributes = [
        'config_version' => 1,
    ];

    protected $fillable = [
        'slug',
        'name',
        'location',
        'featured_product_id',
        'activation_code_hash',
        'device_token_hash',
        'config_version',
        'last_seen_at',
        'media_status',
        'is_active',
    ];

    protected $hidden = [
        'activation_code_hash',
        'device_token_hash',
    ];

    protected $casts = [
        'config_version' => 'integer',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function featuredProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'featured_product_id');
    }

    public function recommendations(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'tablet_recommendations')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TabletConfigVersion::class)->orderByDesc('version_number');
    }

    public function incrementConfigVersion(): void
    {
        $this->forceFill(['config_version' => $this->config_version + 1])->save();
    }
}
