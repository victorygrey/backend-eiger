<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FitAndGoCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'display_name',
        'mc_level',
        'mc_keywords',
        'background_image',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cat) {
            if (empty($cat->name)) {
                $cat->name = $cat->display_name ?? $cat->code;
            }
        });
    }

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function itemVisibilities(): HasMany
    {
        return $this->hasMany(FitAndGoItemVisibility::class, 'category_code', 'code');
    }
}
