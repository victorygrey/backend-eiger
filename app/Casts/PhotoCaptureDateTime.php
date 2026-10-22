<?php

namespace App\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PhotoCaptureDateTime implements CastsAttributes
{
    public bool $withoutObjectCaching = true;

    private function timezone(array $attributes): string
    {
        // Fitting API timestamps are stored in UTC, independently of the CMS
        // display timezone. Preserve the older history rows' original convention.
        return ($attributes['storage_disk'] ?? null) === 'fit-photos'
            ? 'UTC' : config('app.timezone', 'UTC');
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, $this->timezone($attributes));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        return $date->copy()->setTimezone($this->timezone($attributes))->format($model->getDateFormat());
    }
}
