<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductActivity;
use Illuminate\Support\Str;

class LedAmbienceActivityClassifier
{
    /** @var list<string> */
    private const PRIORITY = ['mountaineering', 'lifestyle', 'tactical', 'riding'];

    /** @var array<string, list<string>> */
    private const ACTIVITY_MAP = [
        'mountaineering' => [
            'camping', 'day-hike', 'hiking', 'summit', 'climbing', 'running',
            'running-training', 'casual-sport', 'flexibility',
        ],
        'lifestyle' => ['daily-wear', 'travelling', 'traveling', 'commute', 'trekking'],
        'tactical' => ['tactical', 'combat', 'daily-mission', 'range', 'shooting'],
        'riding' => ['riding', 'day-ride', 'touring', 'cycling'],
    ];

    /** @var array<string, string> */
    private const MASTER_GROUP_MAP = [
        'camping-hiking' => 'mountaineering',
        'climbing' => 'mountaineering',
        'running' => 'mountaineering',
        'daily-wear' => 'lifestyle',
        'travelling' => 'lifestyle',
        'traveling' => 'lifestyle',
        'tactical' => 'tactical',
        'shooting' => 'tactical',
        'riding' => 'riding',
        'cycling' => 'riding',
    ];

    /**
     * @return array{key:string,name:string,votes:int,score:float,matched_activities:list<array<string, mixed>>,counts:array<string, int>}|null
     */
    public function classify(Product $product): ?array
    {
        $product->loadMissing('activitiesRelation.atomActivity.group');

        $buckets = collect(self::PRIORITY)->mapWithKeys(fn (string $key): array => [
            $key => ['votes' => 0, 'score' => 0.0, 'matched' => []],
        ])->all();

        foreach ($product->activitiesRelation as $activity) {
            $group = $this->groupFor($activity);
            if (! $group) {
                continue;
            }

            $buckets[$group]['votes']++;
            $buckets[$group]['score'] += $this->normalizedScore($activity);
            $buckets[$group]['matched'][] = [
                'name' => $activity->atomActivity?->name ?? $activity->name,
                'slug' => $activity->atomActivity?->slug ?? Str::slug((string) $activity->name),
                'selected' => $activity->selected_rating === null
                    ? $activity->is_selected
                    : (float) $activity->selected_rating,
                'rating' => $activity->rating === null ? null : (float) $activity->rating,
            ];
        }

        $winner = collect(self::PRIORITY)
            ->filter(fn (string $key): bool => $buckets[$key]['votes'] > 0)
            ->sort(function (string $left, string $right) use ($buckets): int {
                $votes = $buckets[$right]['votes'] <=> $buckets[$left]['votes'];
                if ($votes !== 0) {
                    return $votes;
                }

                $score = $buckets[$right]['score'] <=> $buckets[$left]['score'];
                if ($score !== 0) {
                    return $score;
                }

                return array_search($left, self::PRIORITY, true) <=> array_search($right, self::PRIORITY, true);
            })
            ->first();

        if (! $winner) {
            return null;
        }

        return [
            'key' => $winner,
            'name' => (string) data_get(config('led_ambience.templates'), $winner.'.name', Str::headline($winner)),
            'votes' => $buckets[$winner]['votes'],
            'score' => round($buckets[$winner]['score'], 4),
            'matched_activities' => $buckets[$winner]['matched'],
            'counts' => collect($buckets)->map(fn (array $bucket): int => $bucket['votes'])->all(),
        ];
    }

    private function groupFor(ProductActivity $activity): ?string
    {
        $activitySlug = Str::slug((string) ($activity->atomActivity?->name ?? $activity->name));

        foreach (self::ACTIVITY_MAP as $group => $slugs) {
            if (in_array($activitySlug, $slugs, true)) {
                return $group;
            }
        }

        $masterGroup = Str::slug((string) ($activity->atomActivity?->group?->name ?? ''));

        return self::MASTER_GROUP_MAP[$masterGroup] ?? null;
    }

    private function normalizedScore(ProductActivity $activity): float
    {
        $selected = $activity->selected_rating === null
            ? ($activity->is_selected ? 1.0 : 0.0)
            : (float) $activity->selected_rating;
        $maximum = (float) ($activity->rating ?: 0);

        return $maximum > 0 ? min(1, max(0, $selected / $maximum)) : max(0, $selected);
    }
}
