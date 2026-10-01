<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    use HasFactory;

    public const STATUSES = ['queued', 'printing', 'success', 'failed', 'cancelled'];

    protected $fillable = [
        'photo_capture_id',
        'print_code',
        'printer_name',
        'copies',
        'status',
        'error_message',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'copies' => 'integer',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function photoCapture(): BelongsTo
    {
        return $this->belongsTo(PhotoCapture::class);
    }
}
