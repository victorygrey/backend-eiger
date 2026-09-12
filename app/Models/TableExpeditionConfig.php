<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableExpeditionConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = static::where('key', $key)->first();
        if (!$config) {
            return $default;
        }

        $decoded = json_decode($config->value, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            return $decoded;
        }

        return $config->value;
    }

    public static function set(string $key, mixed $value): self
    {
        $valToStore = (is_array($value) || is_object($value)) ? json_encode($value) : $value;
        return static::updateOrCreate(['key' => $key], ['value' => $valToStore]);
    }
}
