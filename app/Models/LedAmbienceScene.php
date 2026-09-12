<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedAmbienceScene extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'scene_type',
        'activity_slug',
        'video_url',
        'audio_url',
        'lighting_color',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(LedAmbienceItem::class, 'scene_id');
    }

    public function scopeIdle(Builder $query): Builder
    {
        return $query->where('scene_type', 'idle')->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
