<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use App\Services\FitAndGoProductClassifier;
use App\Services\FitAndGoProductPresentation;
use Illuminate\Http\JsonResponse;
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
    public function index(Request $request, FitAndGoProductClassifier $classifier, FitAndGoProductPresentation $presentation): View
    {
        $currentTab = $request->query('tab', 'products');
        if ($currentTab === 'devices') {
            $currentTab = 'kiosks';
        }
        if (! in_array($currentTab, ['products', 'kiosks'], true)) {
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
            ->latest('created_at')->latest('id')
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
        $activeSelectedProductIds = array_values(array_intersect(
            $selectedProductIds,
            $configuredProductIds
        ));
        $configuredProductCount = count($activeSelectedProductIds);

        return view('admin.fit-and-go.index', [
            'currentTab' => $currentTab,
            'aiProducts' => $aiProducts,
            'productGroups' => $productGroups,
            'wahanaOptions' => $wahanaOptions,
            'kioskDevices' => $kioskDevices,
            'selectedDevice' => $selectedDevice,
            'selectedProductIds' => $selectedProductIds,
            'selectionMode' => $selectionMode,
            'configuredProductCount' => $configuredProductCount,
            'configuredProductIds' => $configuredProductIds,
            'presentation' => $presentation,
        ]);
    }

    public function updateProductConfig(
        Request $request,
        FitAndGoDevice $device,
        FitAndGoProductClassifier $classifier
    ): RedirectResponse {
        abort_unless($device->isStaticSlot(), 404);

        $validated = $request->validate([
            'selection_mode' => 'required|in:latest,selected',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|distinct|exists:products,id',
        ]);
        $productIds = array_values($validated['product_ids'] ?? []);

        // Older clients may switch modes without submitting a new selection.
        // The form sends selection_submitted even when every checkbox is cleared.
        if ($validated['selection_mode'] === 'selected' || $request->has('product_ids') || $request->boolean('selection_submitted')) {
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

            DB::transaction(function () use ($device, $eligibleProducts, $classifier, $validated) {
                FitAndGoItemVisibility::where('device_id', $device->id)->delete();
                foreach ($eligibleProducts as $product) {
                    FitAndGoItemVisibility::create([
                        'device_id' => $device->id,
                        'product_id' => $product->id,
                        'category_code' => $classifier->groupCode($product),
                        'is_visible' => true,
                    ]);
                }
                $device->update(['product_selection_mode' => $validated['selection_mode']]);
            });
        } else {
            $device->update(['product_selection_mode' => $validated['selection_mode']]);
        }

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

        $colors = $presentation->colors($product);

        return response()->json([
            'product' => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku],
            'photos' => $presentation->photos($product),
            'colors' => $colors,
            'image' => $colors[0]['image'],
            'description' => $colors[0]['description'],
            'configured' => $presentation->isConfigured($product),
        ]);
    }

    public function updateProductPresentation(Request $request, Product $product, FitAndGoProductPresentation $presentation): JsonResponse
    {
        abort_unless(Product::query()->aiFitAndGoCatalog()->whereKey($product->id)->exists(), 404);
        $groups = collect($presentation->colors($product))->keyBy('key');
        $perColor = $request->has('colors');
        if ($perColor) {
            $data = $request->validate([
                'colors' => ['required', 'array', 'list', 'min:1', 'max:100'],
                'colors.*.key' => ['required', 'string', 'distinct'],
                'colors.*.image' => ['required', 'string', 'max:16384'],
                'colors.*.description' => ['required', 'string', 'max:5000'],
                'colors.*.photo_descriptions' => ['sometimes', 'array', 'max:200'],
                'colors.*.photo_descriptions.*' => ['nullable', 'string', 'max:5000'],
            ]);
            $rows = $data['colors'];
        } else {
            $data = $request->validate([
                'image' => ['required', 'string', 'max:16384'],
                'description' => ['required', 'string', 'max:5000'],
            ]);
            $rows = [['key' => $groups->keys()->first(), ...$data]];
        }
        if (count($rows) !== $groups->count() || collect($rows)->pluck('key')->diff($groups->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['colors' => 'Pilih foto dan isi deskripsi untuk setiap warna produk. Muat ulang popup jika varian berubah.']);
        }

        $colors = [];
        foreach ($rows as $index => $row) {
            $group = $groups[$row['key']];
            $prefix = $perColor ? "colors.{$index}." : '';
            $image = $presentation->imageKey($row['image']);
            $allowed = array_column($group['photos'], 'value');
            if (! in_array($image, $allowed, true)) {
                throw ValidationException::withMessages([$prefix.'image' => "Pilih foto dari galeri warna {$group['name']}."]);
            }
            $description = trim($row['description']);
            if ($description === '') {
                throw ValidationException::withMessages([$prefix.'description' => "Deskripsi warna {$group['name']} wajib diisi."]);
            }
            $drafts = [];
            foreach ($row['photo_descriptions'] ?? [] as $url => $draft) {
                $key = $presentation->imageKey((string) $url);
                if (! in_array($key, $allowed, true)) {
                    throw ValidationException::withMessages([$prefix.'photo_descriptions' => 'Deskripsi foto harus berasal dari galeri warna yang dipilih.']);
                }
                $drafts[$key] = trim((string) $draft);
            }
            $drafts[$image] = $description;
            $colors[$row['key']] = ['image' => $image, 'description' => $description, 'photo_descriptions' => $drafts];
        }
        // Write once after every color has passed validation; top-level fields keep compatibility.
        $primary = $colors[$groups->keys()->first()];
        $product->fitAndGoConfiguration()->updateOrCreate([], [
            'image' => $primary['image'], 'description' => $primary['description'], 'colors' => $colors,
        ]);

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
            'status' => 'online',
        ]);

        return redirect()->route('admin.fit-and-go.index', ['tab' => 'kiosks'])
            ->with('success', "Status perangkat {$device->name} berhasil diperiksa (Online).");
    }
}
