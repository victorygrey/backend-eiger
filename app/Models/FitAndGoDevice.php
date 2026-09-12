<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
