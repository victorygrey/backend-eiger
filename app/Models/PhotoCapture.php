<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PhotoCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'capture_code',
        'fit_and_go_device_id',
        'session_reference',
        'photo_path',
        'thumbnail_path',
        'storage_disk',
        'captured_at',
        'expires_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(FitAndGoDevice::class, 'fit_and_go_device_id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function latestPrintJob(): HasOne
    {
        return $this->hasOne(PrintJob::class)->latestOfMany('requested_at');
    }
}
