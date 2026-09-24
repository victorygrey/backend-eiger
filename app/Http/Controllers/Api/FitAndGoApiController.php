<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use App\Support\DeviceProductPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FitAndGoApiController extends Controller
{
    /**
     * Get the complete configuration for one kiosk by its URL slug/device identifier.
     * GET /api/v1/fit-and-go/kiosks/{deviceCode}
     */
    public function kiosk(string $deviceCode): JsonResponse
    {
        $device = FitAndGoDevice::whereRaw('LOWER(device_code) = ?', [Str::lower($deviceCode)])->first();

        if (! $device || ! $device->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => "Kiosk dengan slug '{$deviceCode}' tidak ditemukan atau tidak aktif.",
                'data' => null,
            ], 404);
        }

        $activities = FitAndGoActivity::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (FitAndGoActivity $activity) use ($device): array {
                $products = $this->recommendedProducts($activity, $device);

                return [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'slug' => $activity->slug,
                    'image' => $activity->image,
                    'description' => $activity->description,
                    'care_mc_level_2' => $activity->care_mc_level_2,
                    'sort_order' => $activity->sort_order,
                    'recommendation_count' => $products->count(),
                    'recommended_items' => $products
                        ->map(fn (Product $product) => DeviceProductPayload::make($product))
                        ->values(),
                ];
            });

        $categories = FitAndGoCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (FitAndGoCategory $category) use ($device): array {
                $productIds = $this->visibleProductIds($category->code, $device);
                $positions = collect($productIds)->flip();
                $products = Product::with(DeviceProductPayload::relations())
                    ->whereIn('id', $productIds)
                    ->where('pim_catalog_active', true)
                    ->where('is_discontinued', false)
                    ->get()
                    ->sortBy(fn (Product $product) => $positions[$product->id] ?? PHP_INT_MAX)
                    ->values();

                return [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->display_name,
                    'mc_level' => $category->mc_level,
                    'background_image' => $category->background_image,
                    'product_count' => $products->count(),
                    'shown_items' => $products
                        ->map(fn (Product $product) => DeviceProductPayload::make($product))
                        ->values(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'kiosk' => [
                    'id' => $device->id,
                    'slug' => $device->device_code,
                    'device_code' => $device->device_code,
                    'name' => $device->name,
                    'location' => $device->location,
                    'status' => $device->status,
                    'gpu_endpoint' => $device->gpu_endpoint,
                    'camera_source' => $device->camera_source,
                    'last_heartbeat' => $device->last_heartbeat_at?->toIso8601String(),
                ],
                'categories' => $categories,
                'activities' => $activities,
            ],
        ]);
    }

    /**
     * Get Kiosk & GPU workstation configuration.
     * GET /api/v1/fit-and-go/config
     */
    public function config(Request $request): JsonResponse
    {
        $deviceCode = $request->query('device_code');

        $query = FitAndGoDevice::where('is_active', true);
        if ($deviceCode) {
            $device = $query->where('device_code', $deviceCode)->first();
        } else {
            $device = $query->first();
        }

        if (! $device) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active Fit & Go device configured.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'device_name' => $device->name,
                'location' => $device->location,
                'gpu_endpoint' => $device->gpu_endpoint,
                'camera_source' => $device->camera_source,
                'device_status' => $device->status,
                'last_heartbeat' => $device->last_heartbeat_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get list of active EIGER outdoor activities (SRS page 10).
     * GET /api/v1/fit-and-go/activities
     */
    public function activities(Request $request): JsonResponse
    {
        $device = $request->filled('device_code')
            ? FitAndGoDevice::where('device_code', $request->query('device_code'))->first()
            : null;

        $activities = FitAndGoActivity::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'image', 'care_mc_level_2', 'description', 'sort_order'])
            ->map(function (FitAndGoActivity $activity) use ($device): array {
                $products = $this->recommendedProducts($activity, $device);

                return [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'slug' => $activity->slug,
                    'image' => $activity->image,
                    'care_mc_level_2' => $activity->care_mc_level_2,
                    'description' => $activity->description,
                    'sort_order' => $activity->sort_order,
                    'recommendation_count' => $products->count(),
                    'recommended_items' => $products->map(fn (Product $product) => DeviceProductPayload::make($product))->values(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'count' => $activities->count(),
            'data' => $activities,
        ]);
    }

    /**
     * Get the ordered recommendation list for one activity.
     * GET /api/v1/fit-and-go/activities/{activity}/recommendations
     */
    public function recommendations(Request $request, FitAndGoActivity $activity): JsonResponse
    {
        abort_unless($activity->is_active, 404);

        $device = $request->filled('device_code')
            ? FitAndGoDevice::where('device_code', $request->query('device_code'))->first()
            : null;
        $products = $this->recommendedProducts($activity, $device);

        return response()->json([
            'status' => 'success',
            'activity' => [
                'id' => $activity->id,
                'name' => $activity->name,
                'slug' => $activity->slug,
            ],
            'device_code' => $device?->device_code,
            'count' => $products->count(),
            'data' => $products->map(fn (Product $product) => DeviceProductPayload::make($product))->values(),
        ]);
    }

    /**
     * Get list of active categories with background presets (SRS page 10, FR-CMS-03).
     * GET /api/v1/fit-and-go/categories
     */
    public function categories(): JsonResponse
    {
        $categories = FitAndGoCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'display_name', 'mc_level', 'background_image', 'mc_keywords', 'sort_order']);

        return response()->json([
            'status' => 'success',
            'count' => $categories->count(),
            'data' => $categories,
        ]);
    }

    /**
     * Get manually assigned kiosk products, optionally intersected with the
     * curated recommendations for an activity.
     * GET /api/v1/fit-and-go/products
     */
    public function products(Request $request): JsonResponse
    {
        $categoryParam = $request->query('category');
        $activityParam = $request->query('activity');
        $deviceCode = $request->query('device_code');
        $limit = max(1, min((int) $request->query('limit', 15), 50));

        $device = null;
        if ($deviceCode) {
            $device = FitAndGoDevice::where('device_code', $deviceCode)->first();
        }

        $query = Product::query()->where('is_discontinued', false)->where('pim_catalog_active', true);
        if ($categoryParam) {
            $category = FitAndGoCategory::where('is_active', true)
                ->where(function ($q) use ($categoryParam) {
                    $q->where('code', $categoryParam)->orWhere('display_name', $categoryParam);
                })->first();
            $query->whereIn('id', $category
                ? $this->visibleProductIds($category->code, $device)
                : []);
        } elseif (! $activityParam) {
            $query->whereIn('id', $this->visibleProductIds(null, $device));
        }
        if ($activityParam) {
            $activity = FitAndGoActivity::where('is_active', true)
                ->where(function ($q) use ($activityParam) {
                    $q->where('slug', $activityParam)->orWhere('name', $activityParam);
                })->first();
            $activityProductIds = [];
            if ($activity) {
                $activityProductIds = $device
                    ? DB::table('fit_and_go_device_activity_products')
                        ->where('device_id', $device->id)->where('activity_id', $activity->id)
                        ->orderBy('sort_order')->pluck('product_id')
                    : $activity->recommendedProducts()->pluck('products.id');
            }
            $query->whereIn('id', $activityProductIds);
        }

        // Sort latest and limit (default 15 per SRS recommendation)
        $products = $query->with(DeviceProductPayload::relations())->latest('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $products->count(),
            'filter' => [
                'category' => $categoryParam,
                'activity' => $activityParam,
                'device_code' => $deviceCode,
                'limit' => $limit,
            ],
            'data' => $products->map(fn (Product $product) => DeviceProductPayload::make($product)),
        ]);
    }

    /**
     * Get the complete detail of the item currently shown by a kiosk.
     * GET /api/v1/fit-and-go/products/{product}
     */
    public function showProduct(Product $product): JsonResponse
    {
        abort_if($product->is_discontinued || ! $product->pim_catalog_active, 404);

        return response()->json([
            'status' => 'success',
            'data' => DeviceProductPayload::make($product),
        ]);
    }

    /**
     * Search products for Kiosk virtual try-on.
     * GET /api/v1/fit-and-go/search?q=...
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        $deviceCode = $request->query('device_code');
        $limit = max(1, min((int) $request->query('limit', 15), 50));

        if (empty($q)) {
            return response()->json([
                'status' => 'success',
                'count' => 0,
                'data' => [],
            ]);
        }

        $device = null;
        if ($deviceCode) {
            $device = FitAndGoDevice::where('device_code', $deviceCode)->first();
        }

        $query = Product::query()->where('pim_catalog_active', true)->where('is_discontinued', false)
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('customAttributesRelation', fn ($attributes) => $attributes
                        ->where('attribute_code', 'like', "%{$q}%")
                        ->orWhere('value', 'like', "%{$q}%"))
                    ->orWhereHas('technologiesRelation', fn ($technologies) => $technologies
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%"))
                    ->orWhereHas('activitiesRelation', fn ($activities) => $activities
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%"))
                    ->orWhereHas('performancesRelation', fn ($performances) => $performances
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('rating_description', 'like', "%{$q}%"))
                    ->orWhereHas('specificationsRelation', fn ($specifications) => $specifications
                        ->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('value', 'like', "%{$q}%"));
            });

        $query->whereIn('id', $this->visibleProductIds(null, $device));

        $products = $query->with(DeviceProductPayload::relations())->latest('id')->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'query' => $q,
            'count' => $products->count(),
            'data' => $products->map(fn (Product $product) => DeviceProductPayload::make($product)),
        ]);
    }

    /** @return Collection<int, Product> */
    private function recommendedProducts(FitAndGoActivity $activity, ?FitAndGoDevice $device): Collection
    {
        $ids = $device
            ? DB::table('fit_and_go_device_activity_products')
                ->where('device_id', $device->id)
                ->where('activity_id', $activity->id)
                ->orderBy('sort_order')
                ->pluck('product_id')
            : $activity->recommendedProducts()->pluck('products.id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $positions = $ids->values()->flip();

        return Product::with(DeviceProductPayload::relations())
            ->whereIn('id', $ids)
            ->where('pim_catalog_active', true)
            ->where('is_discontinued', false)
            ->get()
            ->sortBy(fn (Product $product) => $positions[$product->id] ?? PHP_INT_MAX)
            ->values();
    }

    private function visibleProductIds(?string $categoryCode, ?FitAndGoDevice $device): array
    {
        $rows = FitAndGoItemVisibility::query()
            ->when($categoryCode, fn ($q) => $q->where('category_code', $categoryCode))
            ->where(function ($q) use ($device) {
                $q->whereNull('device_id');
                if ($device) {
                    $q->orWhere('device_id', $device->id);
                }
            })
            ->orderByRaw('CASE WHEN device_id IS NULL THEN 0 ELSE 1 END DESC')
            ->latest('id')->get();

        return $rows->unique(fn ($row) => $row->category_code.':'.$row->product_id)
            ->where('is_visible', true)->pluck('product_id')->unique()->values()->all();
    }

    /**
     * Kiosk device heartbeat.
     * POST /api/v1/fit-and-go/heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_code' => 'required|string|exists:fit_and_go_devices,device_code',
            'status' => 'nullable|string|in:online,offline,active,maintenance',
        ]);

        $device = FitAndGoDevice::where('device_code', $validated['device_code'])->firstOrFail();
        $device->update([
            'last_heartbeat_at' => now(),
            'status' => $validated['status'] ?? 'online',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Heartbeat received for device {$device->device_code}.",
            'data' => [
                'device_code' => $device->device_code,
                'status' => $device->status,
                'last_heartbeat_at' => $device->last_heartbeat_at->toIso8601String(),
            ],
        ]);
    }
}
