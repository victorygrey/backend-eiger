<?php

namespace App\Services;

class MasterDataAttributeTranslator
{
    public function __construct(private readonly AtomMasterDataResolver $resolver) {}

    /**
     * Replace PIM category and activity codes with canonical master-data names.
     * The original/canonical code remains available in master_values for tracing,
     * while clients can consistently use `value` as the display value.
     *
     * @param  array<int, array<string, mixed>>  $attributes
     * @return array<int, array<string, mixed>>
     */
    public function translate(array $attributes): array
    {
        return collect($attributes)->map(function (mixed $attribute): mixed {
            if (! is_array($attribute)) {
                return $attribute;
            }

            $value = $attribute['value'] ?? null;
            if (! is_scalar($value)) {
                return $attribute;
            }

            $masterValues = $this->resolver->displayValues(
                (string) $value,
                (string) ($attribute['attributeCode'] ?? ''),
            );

            if ($masterValues === []) {
                return $attribute;
            }

            $attribute['value'] = collect($masterValues)
                ->pluck('name')
                ->filter()
                ->unique()
                ->implode(', ');
            $attribute['master_values'] = $masterValues;

            return $attribute;
        })->values()->all();
    }
}
