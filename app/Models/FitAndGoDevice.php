<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FitAndGoDevice extends Model
{
    use HasFactory;

    public const STATIC_SLOTS = [
        1 => [
            'name' => 'AI Fit & Go Lantai 1',
            'device_code' => 'fit-and-go-lantai-1',
            'location' => 'Lantai 1',
        ],
        2 => [
            'name' => 'AI Fit & Go Lantai 2',
            'device_code' => 'fit-and-go-lantai-2',
            'location' => 'Lantai 2',
        ],
        3 => [
            'name' => 'AI Fit & Go Lantai 3',
            'device_code' => 'fit-and-go-lantai-3',
            'location' => 'Lantai 3',
        ],
    ];

    public static function staticDeviceCodes(): array
    {
        return array_column(self::STATIC_SLOTS, 'device_code');
    }

    public function isStaticSlot(): bool
    {
        return in_array($this->device_code, self::staticDeviceCodes(), true);
    }

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
