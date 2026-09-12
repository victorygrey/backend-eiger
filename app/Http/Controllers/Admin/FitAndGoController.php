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
     * Display AI Fit & Go configuration dashboard (Devices, Activities, Categories).
     */
    public function index(Request $request): View
    {
        $currentTab = $request->query('tab', 'devices');
        $selectedCat = $request->query('category', 'hat');

        $devices = FitAndGoDevice::orderBy('id')->get();
        $activities = FitAndGoActivity::orderBy('sort_order')->get();
        $categories = FitAndGoCategory::orderBy('sort_order')->get();

        // Get products for the selected category
        $activeCategory = $categories->firstWhere('code', $selectedCat) ?? $categories->first();
        $categoryProducts = collect();

        if ($activeCategory) {
            $keywords = array_filter(array_map('trim', explode(',', strtolower($activeCategory->mc_keywords ?? ''))));
            
            $query = Product::query();
            if (!empty($keywords)) {
                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('name', 'like', "%{$kw}%")
                          ->orWhere('description', 'like', "%{$kw}%")
                          ->orWhere('pim_payload', 'like', "%{$kw}%");
                    }
                });
            }

            $rawProducts = $query->orderBy('name')->get();

            // Attach visibility status
            $visibilities = FitAndGoItemVisibility::where('category_code', $activeCategory->code)
                ->pluck('is_visible', 'product_id');

            $categoryProducts = $rawProducts->map(function ($p) use ($visibilities) {
                $p->is_fit_visible = $visibilities->get($p->id, true);
                return $p;
            });
        }

        return view('admin.fit-and-go.index', [
            'currentTab'        => $currentTab,
            'selectedCategory'  => $activeCategory,
            'devices'           => $devices,
            'activities'        => $activities,
            'categories'        => $categories,
            'categoryProducts'  => $categoryProducts,
        ]);
    }

    // ==========================================
    // DEVICE CONFIGURATION ACTIONS
    // ==========================================

    public function storeDevice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'device_code'   => 'required|string|max:50|unique:fit_and_go_devices,device_code',
            'location'      => 'nullable|string|max:100',
            'ip_address'    => 'nullable|ip',
            'gpu_endpoint'  => 'nullable|url|max:255',
            'camera_source' => 'nullable|string|max:100',
            'status'        => 'required|in:online,offline,active,maintenance',
            'is_active'     => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['last_heartbeat_at'] = now();

        FitAndGoDevice::create($validated);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'devices'])
            ->with('success', "Perangkat {$validated['name']} berhasil ditambahkan.");
    }

    public function updateDevice(Request $request, FitAndGoDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'device_code'   => 'required|string|max:50|unique:fit_and_go_devices,device_code,' . $device->id,
            'location'      => 'nullable|string|max:100',
            'ip_address'    => 'nullable|ip',
            'gpu_endpoint'  => 'nullable|url|max:255',
            'camera_source' => 'nullable|string|max:100',
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

    public function storeActivity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'slug'            => 'nullable|string|max:50|unique:fit_and_go_activities,slug',
            'care_mc_level_2' => 'nullable|string|max:50',
            'image'           => 'nullable|url|max:500',
            'description'     => 'nullable|string|max:500',
            'sort_order'      => 'nullable|integer|min:0',
            'is_active'       => 'nullable|boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        FitAndGoActivity::create($validated);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'activities'])
            ->with('success', "Aktivitas {$validated['name']} berhasil ditambahkan.");
    }

    public function updateActivity(Request $request, FitAndGoActivity $activity): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'slug'            => 'required|string|max:50|unique:fit_and_go_activities,slug,' . $activity->id,
            'care_mc_level_2' => 'nullable|string|max:50',
            'image'           => 'nullable|url|max:500',
            'description'     => 'nullable|string|max:500',
            'sort_order'      => 'nullable|integer|min:0',
            'is_active'       => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $activity->update($validated);

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

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'categories', 'category' => $category->code])
            ->with('success', "Kategori {$category->display_name} berhasil diperbarui.");
    }

    public function toggleItemVisibility(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'category_code' => 'required|string',
            'is_visible'    => 'required|boolean',
        ]);

        FitAndGoItemVisibility::updateOrCreate(
            [
                'product_id'    => $validated['product_id'],
                'category_code' => $validated['category_code'],
            ],
            [
                'is_visible' => (bool) $validated['is_visible'],
            ]
        );

        return back()->with('success', 'Visibilitas produk AI Fit & Go berhasil diubah.');
    }
}
