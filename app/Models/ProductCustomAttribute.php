<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductCustomAttribute extends Model
{
    protected $fillable = ['product_id', 'attribute_code', 'value', 'value_type', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
