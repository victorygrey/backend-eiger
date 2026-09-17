<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductTechnology extends Model
{
    protected $fillable = ['product_id', 'pim_id', 'name', 'description', 'image_url', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
