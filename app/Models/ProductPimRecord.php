<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPimRecord extends Model
{
    protected $fillable = ['product_id', 'generic_sku', 'source', 'schema_version', 'payload_checksum',
        'product_payload', 'image_payload', 'received_at'];

    protected $casts = ['product_payload' => 'array', 'image_payload' => 'array', 'received_at' => 'datetime'];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
