<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FitAndGoDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'device_code',
        'location',
        'ip_address',
        'gpu_endpoint',
        'camera_source',
        'status',
        'is_active',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_heartbeat_at' => 'datetime',
    ];

    public function itemVisibilities(): HasMany
    {
        return $this->hasMany(FitAndGoItemVisibility::class, 'device_id');
    }
}
