<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableExpeditionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfid_tag',
        'product_id',
        'activity_slug',
        'ideal_for',
        'video_url',
        'features',
        'technical_details',
        'ai_summary',
        'similar_product_ids',
        'notes',
        'is_active',
        'last_scanned_at',
    ];

    protected $casts = [
        'features' => 'array',
        'technical_details' => 'array',
        'similar_product_ids' => 'array',
        'is_active' => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActiveFromMasterRfid(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('is_active'), true)
            ->whereExists(function ($rfidQuery): void {
                $rfidQuery->selectRaw('1')
                    ->from('rfid_tags')
                    ->whereNotNull('rfid_tags.product_id')
                    ->whereColumn('rfid_tags.product_id', $this->qualifyColumn('product_id'))
                    ->whereRaw(
                        "UPPER(REPLACE(REPLACE(REPLACE(rfid_tags.uid, '-', ''), ':', ''), ' ', '')) = "
                        ."UPPER(REPLACE(REPLACE(REPLACE({$this->getTable()}.rfid_tag, '-', ''), ':', ''), ' ', ''))",
                    );
            });
    }

    /**
     * Get the master RFID tag info (name & product link) by UID.
     */
    public function rfidTag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class, 'rfid_tag', 'uid');
    }

    /**
     * Get similar products collection resolved from similar_product_ids.
     */
    public function getSimilarProductsAttribute()
    {
        if (empty($this->similar_product_ids)) {
            return collect();
        }

        return Product::whereIn('id', $this->similar_product_ids)
            ->where('is_discontinued', false)
            ->get();
    }
}
