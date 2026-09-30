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
        'product_selection_mode',
        'activation_code_hash',
        'activation_code_encrypted',
        'device_token_hash',
        'last_heartbeat_at',
    ];

    protected $hidden = [
        'activation_code_hash',
        'activation_code_encrypted',
        'device_token_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_heartbeat_at' => 'datetime',
        'activation_code_encrypted' => 'encrypted',
    ];

    public function itemVisibilities(): HasMany
    {
        return $this->hasMany(FitAndGoItemVisibility::class, 'device_id');
    }
}
