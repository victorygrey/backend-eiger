<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductMedia extends Model
{
    protected $table = 'product_media';
    protected $fillable = ['product_id', 'product_variant_id', 'sku', 'external_id', 'attribute_code', 'role',
        'media_type', 'url', 'source_url', 'source', 'description', 'checksum', 'mime_type', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
