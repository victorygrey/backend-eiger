<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductSpecification extends Model
{
    protected $fillable = ['product_id', 'code', 'name', 'value', 'unit', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
