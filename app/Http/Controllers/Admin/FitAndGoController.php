<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use App\Services\FitAndGoProductClassifier;
use App\Services\FitAndGoProductPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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
    public function index(Request $request, FitAndGoProductClassifier $classifier, FitAndGoProductPresentation $presentation): View
    {
        $currentTab = $request->query('tab', 'products');
        if ($currentTab === 'devices') {
            $currentTab = 'kiosks';
        }
        if (!in_array($currentTab, ['products', 'kiosks'], true)) {
            $currentTab = 'products';
        }

        $aiProducts = Product::query()
            ->aiFitAndGoCatalog()
            ->with(FitAndGoProductPresentation::relations())
            ->with([
                'atomCategory',
                'atomSubCategory',
                'activitiesRelation.atomActivity.group',
            ])
            ->withCount('variants')
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
            ->whereIn('device_code', FitAndGoDevice::staticDeviceCodes())
            ->orderBy('device_code')
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
        $configuredProductIds = $presentation->ready($aiProducts)->pluck('id')->all();
        $latestProductIds = array_slice($configuredProductIds, 0, 30);
        $activeSelectedProductIds = array_values(array_intersect(
            $selectedProductIds,
            $configuredProductIds
        ));
        $configuredProductCount = $selectionMode === 'latest'
            ? count($latestProductIds)
            : count($activeSelectedProductIds);

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
            'configuredProductIds' => $configuredProductIds,
        ]);
    }

    public function updateProductConfig(
        Request $request,
        FitAndGoDevice $device,
        FitAndGoProductClassifier $classifier
    ): RedirectResponse
    {
        abort_unless($device->isStaticSlot(), 404);

        $validated = $request->validate([
            'selection_mode' => 'required|in:latest,selected',
            'product_ids' => 'nullable|array|max:30',
            'product_ids.*' => 'integer|distinct|exists:products,id',
        ]);
        $productIds = array_values($validated['product_ids'] ?? []);

        if ($validated['selection_mode'] === 'selected') {
            $eligibleProducts = Product::query()
                ->with(['atomCategory', 'atomSubCategory', ...FitAndGoProductPresentation::relations()])
                ->aiFitAndGoCatalog()
                ->whereIn('id', $productIds)
                ->get();

            if ($eligibleProducts->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'product_ids' => 'Pilihan hanya boleh berisi produk yang aktif untuk AI Fit & Go.',
                ]);
            }

            if (app(FitAndGoProductPresentation::class)->ready($eligibleProducts)->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'product_ids' => 'Pilih foto dan isi deskripsi barang terlebih dahulu. Produk harus berstatus Config Done sebelum dipakai.',
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

    public function productPresentation(Product $product, FitAndGoProductPresentation $presentation): JsonResponse
    {
        abort_unless(Product::query()->aiFitAndGoCatalog()->whereKey($product->id)->exists(), 404);

        return response()->json([
            'product' => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku],
            'photos' => $presentation->photos($product),
            'image' => $product->fitAndGoConfiguration?->image,
            'description' => $product->fitAndGoConfiguration?->description ?? '',
            'configured' => $presentation->isConfigured($product),
        ]);
    }

    public function updateProductPresentation(Request $request, Product $product, FitAndGoProductPresentation $presentation): JsonResponse
    {
        abort_unless(Product::query()->aiFitAndGoCatalog()->whereKey($product->id)->exists(), 404);
        $data = $request->validate([
            'image' => ['required', 'string', 'max:16384'],
            'description' => ['required', 'string', 'max:5000'],
        ]);
        $image = $presentation->imageKey($data['image']);
        $description = trim($data['description']);
        if (! in_array($image, array_column($presentation->photos($product), 'value'), true)) {
            throw ValidationException::withMessages(['image' => 'Pilih foto yang tersedia pada galeri produk ini.']);
        }
        if ($description === '') {
            throw ValidationException::withMessages(['description' => 'Deskripsi barang wajib diisi.']);
        }

        $product->fitAndGoConfiguration()->updateOrCreate([], ['image' => $image, 'description' => $description]);

        return response()->json(['message' => 'Konfigurasi foto dan deskripsi berhasil disimpan.', 'configured' => true]);
    }

    public function editDevice(FitAndGoDevice $device): View
    {
        abort_unless($device->isStaticSlot(), 404);

        return view('admin.fit-and-go.devices.edit', compact('device'));
    }

    public function updateDevice(Request $request, FitAndGoDevice $device): RedirectResponse
    {
        abort_unless($device->isStaticSlot(), 404);

        $validated = $request->validate([
            'activation_code' => 'required|string|min:6|max:64',
        ]);

        $identity = collect(FitAndGoDevice::STATIC_SLOTS)
            ->firstWhere('device_code', $device->device_code);
        $updates = [...$identity, 'is_active' => true];

        if ($validated['activation_code'] !== $device->activation_code_encrypted) {
            $updates['activation_code_hash'] = Hash::make($validated['activation_code']);
            $updates['activation_code_encrypted'] = $validated['activation_code'];
            $updates['device_token_hash'] = null;
        }
        $device->update($updates);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'kiosks'])
            ->with('success', "Konfigurasi perangkat {$device->name} berhasil diperbarui.");
    }

    public function pingDevice(FitAndGoDevice $device): RedirectResponse
    {
        abort_unless($device->isStaticSlot(), 404);

        $device->update([
            'last_heartbeat_at' => now(),
            'status'            => 'online',
        ]);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'kiosks'])
            ->with('success', "Status perangkat {$device->name} berhasil diperiksa (Online).");
    }

}
