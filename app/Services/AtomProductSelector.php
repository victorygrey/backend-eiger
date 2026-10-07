<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class AtomProductSelector
{
    public const ACTIVITY_GROUPS = [
        'running' => ['Running & Training'],
        'camping_hiking' => ['Camping', 'Hiking', 'Day Hike', 'Trekking', 'Summit'],
        'climbing' => ['Climbing'],
        'riding' => ['Day Ride', 'Touring', 'Cycling'],
        'tactical' => ['Tactical', 'Combat', 'Range'],
        'daily_wear' => ['Daily Wear', 'Commute', 'Daily Mission', 'Casual Sport', 'Flexibility'],
        'travelling' => ['Travelling'],
    ];

    private const TYPES = ['hat', 'shirt', 'jacket', 'bag', 'pants', 'shoes'];

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     * @return array<int, array<string, mixed>>
     */
    public function select(array $catalog, int $perType = 50): array
    {
        $perType = max(1, $perType);
        $targets = [];
        foreach (['women', 'men'] as $gender) {
            foreach (self::TYPES as $type) {
                $targets[$gender.'.'.$type] = [
                    'gender' => $gender,
                    'product_type' => $type,
                    'products' => [],
                ];
            }
        }

        $catalog = collect($catalog)
            ->filter(fn ($item) => is_array($item) && $this->eligibleBase($item))
            ->sortBy([
                fn ($a, $b) => ((int) ($b['soldCount'] ?? 0)) <=> ((int) ($a['soldCount'] ?? 0)),
                fn ($a, $b) => strcmp((string) ($a['skuProduct'] ?? ''), (string) ($b['skuProduct'] ?? '')),
            ])->values()->all();
        $used = [];

        // Reserve scarce activity combinations for every bucket before filling quotas.
        foreach (array_keys(self::ACTIVITY_GROUPS) as $activityGroup) {
            foreach ($targets as &$target) {
                $candidate = $this->candidate($catalog, $target, $used, $activityGroup);
                if ($candidate) {
                    $this->append($target, $candidate, $used);
                }
            }
            unset($target);
        }

        foreach ($targets as &$target) {
            while (count($target['products']) < $perType) {
                $counts = collect($target['products'])->countBy('activity_group');
                $groups = collect(array_keys(self::ACTIVITY_GROUPS))
                    ->sortBy(fn ($group) => (int) ($counts[$group] ?? 0));
                $candidate = null;
                foreach ($groups as $group) {
                    $candidate = $this->candidate($catalog, $target, $used, $group);
                    if ($candidate) {
                        break;
                    }
                }
                $candidate ??= $this->candidate($catalog, $target, $used);
                if (! $candidate) {
                    throw new RuntimeException(sprintf(
                        'Katalog ATOM hanya menyediakan %d/%d produk untuk %s %s.',
                        count($target['products']), $perType, $target['product_type'], $target['gender']
                    ));
                }
                $this->append($target, $candidate, $used);
            }
        }
        unset($target);

        return collect($targets)->flatMap(fn ($target) => $target['products'])->values()->all();
    }

    private function append(array &$target, array $candidate, array &$used): void
    {
        $sku = (string) $candidate['skuProduct'];
        $sourceGender = $this->gender($candidate);
        $target['products'][] = [
            'sku' => $sku,
            'slug' => (string) $candidate['slug'],
            'name' => (string) $candidate['name'],
            'gender' => $target['gender'],
            'gender_source' => $sourceGender === $target['gender'] ? 'explicit' : 'neutral_fallback',
            'product_type' => $target['product_type'],
            'activity' => (string) data_get($candidate, 'activity.name', ''),
            'activity_group' => $this->activityGroup($candidate),
        ];
        $used[$sku] = true;
    }

    private function candidate(array $catalog, array $target, array $used, ?string $activityGroup = null): ?array
    {
        $matches = array_values(array_filter($catalog, function ($item) use ($target, $used, $activityGroup) {
            $sku = (string) ($item['skuProduct'] ?? '');
            if ($sku === '' || isset($used[$sku]) || ! $this->matchesType($item, $target['product_type'])) {
                return false;
            }
            $gender = $this->gender($item);
            if (! in_array($gender, [$target['gender'], 'neutral'], true)) {
                return false;
            }

            return $activityGroup === null || $this->activityGroup($item) === $activityGroup;
        }));

        usort($matches, function ($a, $b) use ($target) {
            $aPriority = $this->gender($a) === $target['gender'] ? 0 : 1;
            $bPriority = $this->gender($b) === $target['gender'] ? 0 : 1;

            return [$aPriority, -((int) ($a['soldCount'] ?? 0)), (string) $a['skuProduct']]
                <=> [$bPriority, -((int) ($b['soldCount'] ?? 0)), (string) $b['skuProduct']];
        });

        return $matches[0] ?? null;
    }

    private function eligibleBase(array $item): bool
    {
        $name = (string) ($item['name'] ?? '');

        return ! preg_match('/\b(?:JUNIOR|KIDS?|BOY|GIRL|CHILD)\b/i', $name)
            && trim((string) ($item['skuProduct'] ?? '')) !== ''
            && trim((string) ($item['slug'] ?? '')) !== '';
    }

    private function gender(array $item): string
    {
        $value = ((string) ($item['name'] ?? '')).' '.((string) ($item['slug'] ?? ''));
        if (preg_match('/(?:\b(?:WOMEN|WOMAN|LADIES|LADY)\b|(?:^|[-_])WS(?:[-_]|$)|WOMAN-SHOES)/i', $value)) {
            return 'women';
        }
        if (preg_match('/(?:\b(?:MEN|MAN|PRIA)\b|(?:^|[-_])MEN(?:[-_]|$)|MAN-SHOES)/i', $value)) {
            return 'men';
        }

        return 'neutral';
    }

    private function activityGroup(array $item): string
    {
        $name = (string) data_get($item, 'activity.name', '');
        foreach (self::ACTIVITY_GROUPS as $group => $activities) {
            if (in_array($name, $activities, true)) {
                return $group;
            }
        }

        return 'other';
    }

    private function matchesType(array $item, string $type): bool
    {
        $category = (string) data_get($item, 'category.name', '');
        $subCategory = (string) data_get($item, 'subCategory.name', '');
        $name = Str::upper((string) ($item['name'] ?? ''));

        return match ($type) {
            'hat' => $category === 'Headwear' && in_array($subCategory, ['Caps', 'Beanies', 'Bandana', 'Hats'], true),
            'shirt' => $category === 'Apparel' && ($subCategory === 'Kemeja'
                || ($subCategory === 'Top' && Str::contains($name, ['SHIRT', 'TEE', 'TEES', 'POLO']))),
            'jacket' => $category === 'Apparel' && in_array($subCategory, ['Jacket', 'Sweater', 'Vest'], true),
            'bag' => $category === 'Bags',
            'pants' => $category === 'Apparel' && in_array($subCategory, ['Pants', 'Celana Pendek'], true),
            'shoes' => $category === 'Footwear' && in_array($subCategory, ['Sepatu', 'Sepatu Boots'], true),
            default => false,
        };
    }
}
