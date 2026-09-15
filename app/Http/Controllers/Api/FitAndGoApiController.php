<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FitAndGoApiController extends Controller
{
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

        if (!$device) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No active Fit & Go device configured.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'device_id'       => $device->id,
                'device_code'     => $device->device_code,
                'device_name'     => $device->name,
                'location'        => $device->location,
                'gpu_endpoint'    => $device->gpu_endpoint,
                'camera_source'   => $device->camera_source,
                'device_status'   => $device->status,
                'last_heartbeat'  => $device->last_heartbeat_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get list of active EIGER outdoor activities (SRS page 10).
     * GET /api/v1/fit-and-go/activities
     */
    public function activities(): JsonResponse
    {
        $activities = FitAndGoActivity::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'image', 'care_mc_level_2', 'description', 'sort_order']);

        return response()->json([
            'status' => 'success',
            'count'  => $activities->count(),
            'data'   => $activities,
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
            'count'  => $categories->count(),
            'data'   => $categories,
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
        } else {
            $query->whereIn('id', $this->visibleProductIds(null, $device));
        }
        if ($activityParam) {
            $activity = FitAndGoActivity::where('is_active', true)
                ->where(function ($q) use ($activityParam) {
                    $q->where('slug', $activityParam)->orWhere('name', $activityParam);
                })->first();
            $query->whereIn('id', $activity
                ? $activity->recommendedProducts()->pluck('products.id')
                : []);
        }

        // Sort latest and limit (default 15 per SRS recommendation)
        $products = $query->latest('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'count'  => $products->count(),
            'filter' => [
                'category'    => $categoryParam,
                'activity'    => $activityParam,
                'device_code' => $deviceCode,
                'limit'       => $limit,
            ],
            'data'   => $products->map(function ($p) {
                return [
                    'id'             => $p->id,
                    'sku'            => $p->sku,
                    'name'           => $p->name,
                    'category'       => $p->category,
                    'price'          => (float) $p->price,
                    'stock'          => (int) $p->stock,
                    'image_url'      => $p->image_url,
                    'description'    => $p->description,
                    'material'       => $p->material,
                    'technologies'   => $p->technologies,
                    'activities'     => $p->activities,
                    'specifications' => $p->specifications,
                    'weight'         => $p->weight,
                    'zone'        => $p->zone ? [
                        'id'   => $p->zone->id,
                        'name' => $p->zone->name,
                        'code' => $p->zone->code,
                    ] : null,
                ];
            }),
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
                'count'  => 0,
                'data'   => [],
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
                    ->orWhere('pim_payload', 'like', "%{$q}%");
            });

        $query->whereIn('id', $this->visibleProductIds(null, $device));

        $products = $query->latest('id')->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'query'  => $q,
            'count'  => $products->count(),
            'data'   => $products->map(function ($p) {
                return [
                    'id'             => $p->id,
                    'sku'            => $p->sku,
                    'name'           => $p->name,
                    'category'       => $p->category,
                    'price'          => (float) $p->price,
                    'stock'          => (int) $p->stock,
                    'image_url'      => $p->image_url,
                    'description'    => $p->description,
                    'material'       => $p->material,
                    'technologies'   => $p->technologies,
                    'activities'     => $p->activities,
                    'specifications' => $p->specifications,
                    'weight'         => $p->weight,
                    'zone'        => $p->zone ? [
                        'id'   => $p->zone->id,
                        'name' => $p->zone->name,
                        'code' => $p->zone->code,
                    ] : null,
                ];
            }),
        ]);
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

        return $rows->unique(fn ($row) => $row->category_code . ':' . $row->product_id)
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
            'status'      => 'nullable|string|in:online,offline,active,maintenance',
        ]);

        $device = FitAndGoDevice::where('device_code', $validated['device_code'])->firstOrFail();
        $device->update([
            'last_heartbeat_at' => now(),
            'status'            => $validated['status'] ?? 'online',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Heartbeat received for device {$device->device_code}.",
            'data'    => [
                'device_code'       => $device->device_code,
                'status'            => $device->status,
                'last_heartbeat_at' => $device->last_heartbeat_at->toIso8601String(),
            ],
        ]);
    }
}
