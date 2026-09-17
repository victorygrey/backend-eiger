<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AtomProductActivityGroup extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'sequence_number' => 'integer'];
    public function activities(): HasMany { return $this->hasMany(AtomProductActivity::class, 'activity_group_id'); }
}
