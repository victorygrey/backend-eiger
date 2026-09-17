<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AtomProductSubCategory extends Model
{
    protected $guarded = [];
    protected $casts = ['is_featured' => 'boolean', 'is_active' => 'boolean', 'sequence_number' => 'integer'];
    public function category(): BelongsTo { return $this->belongsTo(AtomProductCategory::class, 'category_id'); }
}
