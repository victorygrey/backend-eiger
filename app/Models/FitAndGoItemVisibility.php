<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FitAndGoItemVisibility extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'product_id',
        'category_code',
        'activity_slug',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FitAndGoDevice::class, 'device_id');
    }
}
