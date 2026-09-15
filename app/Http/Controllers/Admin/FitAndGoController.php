<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FitAndGoController extends Controller
{
    /**
     * Display AI Fit & Go configuration dashboard (Devices, Activities).
     */
    public function index(Request $request): View
    {
        $currentTab = $request->query('tab', 'devices');
        if (!in_array($currentTab, ['devices', 'activities'])) {
            $currentTab = 'devices';
        }

        $devices = FitAndGoDevice::withCount('itemVisibilities')->orderBy('id')->get();
        $showInactive = $request->boolean('show_inactive');
        $activities = FitAndGoActivity::query()
            ->when(!$showInactive, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->get();

        return view('admin.fit-and-go.index', [
            'currentTab' => $currentTab,
            'devices'    => $devices,
            'activities' => $activities,
            'showInactive' => $showInactive,
        ]);
    }

    // ==========================================
    // DEVICE CONFIGURATION ACTIONS
    // ==========================================

    public function createDevice(): View
    {
        return view('admin.fit-and-go.devices.create');
    }

    public function storeDevice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'device_code'   => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:50|unique:fit_and_go_devices,device_code',
            'location'      => 'nullable|string|max:100',
            'ip_address'    => 'nullable|ip',
            'gpu_endpoint'  => 'nullable|url|max:255',
            'camera_source' => 'nullable|string|max:255',
            'status'        => 'required|in:online,offline,active,maintenance',
            'is_active'     => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['last_heartbeat_at'] = now();

        FitAndGoDevice::create($validated);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'devices'])
            ->with('success', "Perangkat {$validated['name']} berhasil ditambahkan.");
    }

    public function editDevice(Request $request, FitAndGoDevice $device): View
    {
        $currentTab = $request->query('tab', 'device');
        $selectedCat = $request->query('category', 'hat');
        $searchQuery = trim($request->query('q', ''));

        $categories = FitAndGoCategory::orderBy('sort_order')->get();
        $activeCategory = $categories->firstWhere('code', $selectedCat) ?? $categories->first();
        $categoryProducts = collect();

        if ($activeCategory) {
            $query = Product::where('is_discontinued', false)->where('pim_catalog_active', true);

            if (!empty($searchQuery)) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('name', 'like', "%{$searchQuery}%")
                      ->orWhere('sku', 'like', "%{$searchQuery}%");
                });
            }

            $rawProducts = $query->orderBy('name')->get();

            // Only explicitly assigned products appear in this kiosk category.
            $visibilities = FitAndGoItemVisibility::where('category_code', $activeCategory->code)
                ->where(function ($q) use ($device) {
                    $q->where('device_id', $device->id)
                      ->orWhereNull('device_id');
                })
                ->orderBy('device_id', 'desc')
                ->get()
                ->unique('product_id')
                ->pluck('is_visible', 'product_id');

            $categoryProducts = $rawProducts->map(function ($p) use ($visibilities) {
                $p->is_fit_visible = $visibilities->get($p->id, false);
                return $p;
            });
        }

        return view('admin.fit-and-go.devices.edit', [
            'device'           => $device,
            'currentTab'       => $currentTab,
            'categories'       => $categories,
            'selectedCategory' => $activeCategory,
            'categoryProducts' => $categoryProducts,
            'searchQuery'      => $searchQuery,
        ]);
    }

    public function updateDevice(Request $request, FitAndGoDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'device_code'   => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:50|unique:fit_and_go_devices,device_code,' . $device->id,
            'location'      => 'nullable|string|max:100',
            'ip_address'    => 'nullable|ip',
            'gpu_endpoint'  => 'nullable|url|max:255',
            'camera_source' => 'nullable|string|max:255',
            'status'        => 'required|in:online,offline,active,maintenance',
            'is_active'     => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $device->update($validated);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'devices'])
            ->with('success', "Konfigurasi perangkat {$device->name} berhasil diperbarui.");
    }

    public function pingDevice(FitAndGoDevice $device): RedirectResponse
    {
        $device->update([
            'last_heartbeat_at' => now(),
            'status'            => 'online',
        ]);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'devices'])
            ->with('success', "Status perangkat {$device->name} berhasil diperiksa (Online).");
    }

    public function destroyDevice(FitAndGoDevice $device): RedirectResponse
    {
        $name = $device->name;
        $device->delete();

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'devices'])
            ->with('success', "Perangkat {$name} berhasil dihapus.");
    }

    // ==========================================
    // ACTIVITY CONFIGURATION ACTIONS
    // ==========================================

    public function createActivity(): View
    {
        $products = Product::where('is_discontinued', false)->where('pim_catalog_active', true)->orderBy('name')->get();
        $selectedProductIds = [];
        return view('admin.fit-and-go.activities.create', compact('products', 'selectedProductIds'));
    }

    public function storeActivity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'slug'            => 'nullable|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:50|unique:fit_and_go_activities,slug',
            'care_mc_level_2' => 'nullable|string|max:50',
            'image'           => 'nullable|url|max:500',
            'description'     => 'nullable|string|max:500',
            'sort_order'      => 'nullable|integer|min:0',
            'is_active'       => 'nullable|boolean',
            'recommended_product_ids' => 'nullable|array',
            'recommended_product_ids.*' => 'integer|distinct|exists:products,id',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $request->merge(['slug' => $validated['slug']]);
        $request->validate(['slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:50|unique:fit_and_go_activities,slug']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $activity = FitAndGoActivity::create($validated);
        $this->syncActivityProducts($request, $activity);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'activities'])
            ->with('success', "Aktivitas {$validated['name']} berhasil ditambahkan.");
    }

    public function editActivity(FitAndGoActivity $activity): View
    {
        $products = Product::where('is_discontinued', false)->where('pim_catalog_active', true)->orderBy('name')->get();
        $selectedProductIds = $activity->recommendedProducts()->pluck('products.id')->all();
        return view('admin.fit-and-go.activities.edit', compact('activity', 'products', 'selectedProductIds'));
    }

    public function updateActivity(Request $request, FitAndGoActivity $activity): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'slug'            => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:50|unique:fit_and_go_activities,slug,' . $activity->id,
            'care_mc_level_2' => 'nullable|string|max:50',
            'image'           => 'nullable|url|max:500',
            'description'     => 'nullable|string|max:500',
            'sort_order'      => 'nullable|integer|min:0',
            'is_active'       => 'nullable|boolean',
            'recommended_product_ids' => 'nullable|array',
            'recommended_product_ids.*' => 'integer|distinct|exists:products,id',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $activity->update($validated);
        $this->syncActivityProducts($request, $activity);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'activities'])
            ->with('success', "Aktivitas {$activity->name} berhasil diperbarui.");
    }

    public function destroyActivity(FitAndGoActivity $activity): RedirectResponse
    {
        $name = $activity->name;
        $activity->delete();

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'activities'])
            ->with('success', "Aktivitas {$name} berhasil dihapus.");
    }

    // ==========================================
    // CATEGORY & VISIBILITY ACTIONS
    // ==========================================

    public function updateCategory(Request $request, FitAndGoCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'display_name'     => 'required|string|max:100',
            'background_image' => 'nullable|url|max:500',
            'mc_keywords'      => 'nullable|string|max:255',
            'is_active'        => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $category->update($validated);

        return back()->with('success', "Kategori {$category->display_name} berhasil diperbarui.");
    }

    public function toggleItemVisibility(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_id'     => 'nullable|exists:fit_and_go_devices,id',
            'product_id'    => 'required|exists:products,id',
            'category_code' => 'required|exists:fit_and_go_categories,code',
            'is_visible'    => 'required|boolean',
        ]);

        FitAndGoItemVisibility::updateOrCreate(
            [
                'device_id'     => $validated['device_id'] ?? null,
                'product_id'    => $validated['product_id'],
                'category_code' => $validated['category_code'],
            ],
            [
                'is_visible' => (bool) $validated['is_visible'],
            ]
        );

        return back()->with('success', 'Visibilitas produk AI Fit & Go berhasil diubah.');
    }

    private function syncActivityProducts(Request $request, FitAndGoActivity $activity): void
    {
        $sync = [];
        foreach ($request->input('recommended_product_ids') ?? [] as $order => $id) {
            $sync[$id] = ['sort_order' => $order];
        }
        $activity->recommendedProducts()->sync($sync);
    }
}
