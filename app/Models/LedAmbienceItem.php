<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedAmbienceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfid_tag',
        'product_id',
        'activity_slug',
        'scene_id',
        'notes',
        'is_active',
        'last_scanned_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(LedAmbienceScene::class, 'scene_id');
    }

    /**
     * Get the master RFID tag info (name & product link) by UID.
     */
    public function rfidTag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class, 'rfid_tag', 'uid');
    }
}
