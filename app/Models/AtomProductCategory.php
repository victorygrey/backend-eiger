<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AtomProductCategory extends Model
{
    protected $guarded = [];
    protected $casts = ['is_featured' => 'boolean', 'is_active' => 'boolean', 'sequence_number' => 'integer'];
    public function subCategories(): HasMany { return $this->hasMany(AtomProductSubCategory::class, 'category_id'); }
}
