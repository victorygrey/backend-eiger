<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfidTag extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rfid_tags';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uid',
        'name',
        'product_id',
        'last_scanned_at',
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
    ];

    /**
     * Get the product this RFID tag is linked to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function canonicalUid(string $uid): string
    {
        return strtoupper(str_replace(['-', ':', ' '], '', trim($uid)));
    }

    public static function recordScan(string $uid): void
    {
        static::query()
            ->where('uid', static::canonicalUid($uid))
            ->update(['last_scanned_at' => now()]);
    }
}
