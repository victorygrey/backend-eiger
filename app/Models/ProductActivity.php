<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductActivity extends Model
{
    protected $fillable = ['product_id', 'pim_id', 'name', 'description', 'is_selected', 'rating', 'rating_description', 'sort_order'];
    protected $casts = ['is_selected' => 'boolean', 'rating' => 'decimal:2', 'sort_order' => 'integer'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
