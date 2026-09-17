<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductVariantAttribute extends Model
{
    protected $fillable = ['product_variant_id', 'attribute_code', 'value', 'value_type', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
