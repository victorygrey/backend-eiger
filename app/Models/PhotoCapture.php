<?php

namespace App\Models;

use App\Casts\PhotoCaptureDateTime;
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
        'idempotency_key',
        'request_hash',
        'gender',
        'activity',
        'background_id',
        'products',
        'image_mime',
        'image_size',
        'consented_at',
        'purged_at',
    ];

    protected $casts = [
        'captured_at' => PhotoCaptureDateTime::class,
        'expires_at' => PhotoCaptureDateTime::class,
        'products' => 'array',
        'consented_at' => PhotoCaptureDateTime::class,
        'purged_at' => PhotoCaptureDateTime::class,
    ];

    protected $hidden = ['photo_path', 'thumbnail_path', 'request_hash', 'idempotency_key'];

    protected $appends = ['photo_url', 'thumbnail_url', 'photo_available'];

    public function getPhotoAvailableAttribute(): bool
    {
        return $this->exists && $this->storage_disk === 'fit-photos' && $this->hasAvailablePhoto();
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_available ? route('admin.print-history.image', $this) : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->photo_available && $this->thumbnail_path
            ? route('admin.print-history.image', ['photoCapture' => $this, 'thumbnail' => 1])
            : null;
    }

    public function hasAvailablePhoto(): bool
    {
        return $this->photo_path && ! $this->purged_at && (! $this->expires_at || $this->expires_at->isFuture());
    }

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
