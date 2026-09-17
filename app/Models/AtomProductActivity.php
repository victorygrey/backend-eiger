<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AtomProductActivity extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function group(): BelongsTo { return $this->belongsTo(AtomProductActivityGroup::class, 'activity_group_id'); }
}
