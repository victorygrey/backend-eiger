<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use App\Services\FitAndGoProductClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FitAndGoController extends Controller
{
    /**
     * Display AI Fit & Go product configuration and activity master data.
     */
    public function index(Request $request, FitAndGoProductClassifier $classifier): View
    {
        $currentTab = $request->query('tab', 'products');
        if ($currentTab === 'devices') {
            $currentTab = 'kiosks';
        }
        if (!in_array($currentTab, ['products', 'kiosks'], true)) {
            $currentTab = 'products';
        }

        $aiProducts = Product::query()
            ->with([
                'atomCategory',
                'atomSubCategory',
                'activitiesRelation.atomActivity.group',
            ])
            ->withCount('variants')
            ->whereRaw('LENGTH(sku) = 9')
            ->where('ai_fit_and_go_active', true)
            ->where('is_discontinued', false)
            ->where(function ($query) {
                $query->whereDoesntHave('pimRecord')->orWhere('pim_catalog_active', true);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(fn ($products) => $products
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            })
            ->latest('updated_at')
            ->get();

        $categoryDefinitions = collect($classifier->definitions());

        $productGroups = $categoryDefinitions->map(function (array $definition, string $key) use ($aiProducts, $classifier) {
            return $definition + [
                'key' => $key,
                'products' => $aiProducts->filter(fn (Product $product) => $classifier->groupCode($product) === $key)->values(),
            ];
        });

        $unmappedProducts = $aiProducts
            ->filter(fn (Product $product) => $classifier->groupCode($product) === 'unmapped')
            ->values();

        if ($unmappedProducts->isNotEmpty()) {
            $productGroups->put('unmapped', [
                'key' => 'unmapped',
                'label' => 'Belum Dikelompokkan',
                'icon' => 'bi-grid',
                'products' => $unmappedProducts,
            ]);
        }

        $kioskDevices = FitAndGoDevice::query()
            ->withCount('itemVisibilities')
            ->orderBy('location')
            ->orderBy('name')
            ->get();
        $wahanaOptions = $kioskDevices
            ->where('is_active', true)
            ->values();

        $selectedDevice = $wahanaOptions->firstWhere('id', $request->integer('device_id'))
            ?? $wahanaOptions->first();
        $selectedProductIds = $selectedDevice
            ? FitAndGoItemVisibility::query()
                ->where('device_id', $selectedDevice->id)
                ->where('is_visible', true)
                ->latest('id')
                ->pluck('product_id')
                ->unique()
                ->values()
                ->all()
            : [];
        $selectionMode = $selectedDevice?->product_selection_mode ?? 'selected';
        $latestProductIds = $aiProducts->take(30)->pluck('id')->all();
        $configuredProductCount = $selectionMode === 'latest'
            ? count($latestProductIds)
            : count($selectedProductIds);

        return view('admin.fit-and-go.index', [
            'currentTab' => $currentTab,
            'aiProducts' => $aiProducts,
            'productGroups' => $productGroups,
            'wahanaOptions' => $wahanaOptions,
            'kioskDevices' => $kioskDevices,
            'selectedDevice' => $selectedDevice,
            'selectedProductIds' => $selectedProductIds,
            'selectionMode' => $selectionMode,
            'latestProductIds' => $latestProductIds,
            'configuredProductCount' => $configuredProductCount,
        ]);
    }

    public function updateProductConfig(
        Request $request,
        FitAndGoDevice $device,
        FitAndGoProductClassifier $classifier
    ): RedirectResponse
    {
        $validated = $request->validate([
            'selection_mode' => 'required|in:latest,selected',
            'product_ids' => 'nullable|array|max:30',
            'product_ids.*' => 'integer|distinct|exists:products,id',
        ]);
        $productIds = array_values($validated['product_ids'] ?? []);

        if ($validated['selection_mode'] === 'selected') {
            $eligibleProducts = Product::query()
                ->with(['atomCategory', 'atomSubCategory'])
                ->whereIn('id', $productIds)
                ->where('ai_fit_and_go_active', true)
                ->where('is_discontinued', false)
                ->where(function ($query) {
                    $query->whereDoesntHave('pimRecord')->orWhere('pim_catalog_active', true);
                })
                ->get();

            if ($eligibleProducts->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'product_ids' => 'Pilihan hanya boleh berisi produk yang aktif untuk AI Fit & Go.',
                ]);
            }

            DB::transaction(function () use ($device, $eligibleProducts, $classifier) {
                FitAndGoItemVisibility::where('device_id', $device->id)->delete();
                foreach ($eligibleProducts as $product) {
                    FitAndGoItemVisibility::create([
                        'device_id' => $device->id,
                        'product_id' => $product->id,
                        'category_code' => $classifier->groupCode($product),
                        'is_visible' => true,
                    ]);
                }
            });
        }

        $device->update(['product_selection_mode' => $validated['selection_mode']]);

        return redirect()->route('admin.fit-and-go.index', [
            'tab' => 'products',
            'device_id' => $device->id,
        ])->with('success', "Konfigurasi produk {$device->name} berhasil disimpan.");
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
            'activation_code' => 'required|string|min:6|max:64',
            'is_active'     => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['status'] = 'offline';
        $validated['activation_code_hash'] = Hash::make($validated['activation_code']);
        $validated['activation_code_encrypted'] = $validated['activation_code'];
        unset($validated['activation_code']);

        FitAndGoDevice::create($validated);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'kiosks'])
            ->with('success', "Perangkat {$validated['name']} berhasil ditambahkan.");
    }

    public function editDevice(FitAndGoDevice $device): View
    {
        return view('admin.fit-and-go.devices.edit', compact('device'));
    }

    public function updateDevice(Request $request, FitAndGoDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'device_code'   => 'required|regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/|max:50|unique:fit_and_go_devices,device_code,' . $device->id,
            'location'      => 'nullable|string|max:100',
            'activation_code' => 'nullable|string|min:6|max:64',
            'is_active'     => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        if ($request->filled('activation_code')
            && $validated['activation_code'] !== $device->activation_code_encrypted) {
            $validated['activation_code_hash'] = Hash::make($validated['activation_code']);
            $validated['activation_code_encrypted'] = $validated['activation_code'];
            $validated['device_token_hash'] = null;
        }
        unset($validated['activation_code']);
        $device->update($validated);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'kiosks'])
            ->with('success', "Konfigurasi perangkat {$device->name} berhasil diperbarui.");
    }

    public function pingDevice(FitAndGoDevice $device): RedirectResponse
    {
        $device->update([
            'last_heartbeat_at' => now(),
            'status'            => 'online',
        ]);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'kiosks'])
            ->with('success', "Status perangkat {$device->name} berhasil diperiksa (Online).");
    }

    public function destroyDevice(FitAndGoDevice $device): RedirectResponse
    {
        $name = $device->name;
        $device->delete();

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'kiosks'])
            ->with('success', "Perangkat {$name} berhasil dihapus.");
    }

}
