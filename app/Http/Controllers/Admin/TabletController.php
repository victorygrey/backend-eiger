<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTabletRequest;
use App\Http\Requests\UpdateTabletRequest;
use App\Models\Product;
use App\Models\Tablet;
use App\Models\TabletConfigVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TabletController extends Controller
{
    public function index()
    {
        $tablets = Tablet::with('featuredProduct')
            ->withCount('recommendations')
            ->latest()
            ->paginate(15);

        return view('admin.tablets.index', compact('tablets'));
    }

    public function create()
    {
        return view('admin.tablets.create', ['products' => $this->availableProducts()]);
    }

    public function store(StoreTabletRequest $request)
    {
        $tablet = DB::transaction(function () use ($request) {
            $tablet = Tablet::create([
                ...$request->safe()->except(['recommendation_ids', 'activation_code', 'is_active']),
                'activation_code_hash' => Hash::make((string) $request->string('activation_code')),
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncRecommendations($tablet, $request->input('recommendation_ids', []));
            $this->createVersionSnapshot($tablet);

            return $tablet;
        });

        return redirect()->route('admin.tablets.edit', $tablet)
            ->with('success', 'Tablet berhasil dibuat. Gunakan kode aktivasi yang baru diatur pada perangkat.');
    }

    public function edit(Tablet $tablet)
    {
        $tablet->load(['recommendations', 'versions.featuredProduct']);

        return view('admin.tablets.edit', [
            'tablet' => $tablet,
            'products' => $this->availableProducts(),
        ]);
    }

    public function update(UpdateTabletRequest $request, Tablet $tablet)
    {
        DB::transaction(function () use ($request, $tablet) {
            $tablet->fill([
                ...$request->safe()->except(['recommendation_ids', 'activation_code', 'is_active']),
                'is_active' => $request->boolean('is_active'),
            ]);

            if ($request->filled('activation_code')) {
                $tablet->activation_code_hash = Hash::make((string) $request->string('activation_code'));
                $tablet->device_token_hash = null;
            }

            $tablet->config_version++;
            $tablet->save();
            $this->syncRecommendations($tablet, $request->input('recommendation_ids', []));
            $this->createVersionSnapshot($tablet);
        });

        return redirect()->route('admin.tablets.edit', $tablet)
            ->with('success', 'Konfigurasi tablet dipublikasikan sebagai versi '.$tablet->config_version.'.');
    }

    public function destroy(Tablet $tablet)
    {
        $tablet->delete();

        return redirect()->route('admin.tablets.index')->with('success', 'Tablet berhasil dihapus.');
    }

    public function rollback(Tablet $tablet, TabletConfigVersion $version)
    {
        if ($version->tablet_id !== $tablet->id) {
            abort(404);
        }

        $featured = Product::whereKey($version->featured_product_id)
            ->where('is_discontinued', false)
            ->first();
        $recommendationIds = Product::whereIn('id', $version->recommendation_product_ids ?? [])
            ->where('is_discontinued', false)
            ->pluck('id')
            ->all();

        if (! $featured || $recommendationIds === []) {
            return redirect()->route('admin.tablets.edit', $tablet)
                ->with('error', 'Versi lama tidak dapat dipulihkan karena produknya sudah tidak tersedia.');
        }

        DB::transaction(function () use ($tablet, $featured, $recommendationIds) {
            // Update the featured product
            $tablet->forceFill([
                'featured_product_id' => $featured->id,
            ])->save();
            // Increment config_version atomically
            $tablet->increment('config_version');
            // Refresh the model to have the latest config_version value
            $tablet->refresh();
            $this->syncRecommendations($tablet, $recommendationIds);
            $this->createVersionSnapshot($tablet);
        });

        return redirect()->route('admin.tablets.edit', $tablet)
            ->with('success', 'Versi lama dipulihkan dan dipublikasikan sebagai versi '.$tablet->config_version.'.');
    }

    private function availableProducts()
    {
        return Product::with('zone')
            ->where('is_discontinued', false)
            ->orderBy('name')
            ->get();
    }

    private function syncRecommendations(Tablet $tablet, array $productIds): void
    {
        $syncData = collect($productIds)
            ->values()
            ->mapWithKeys(fn ($productId, $index) => [(int) $productId => ['sort_order' => $index]])
            ->all();

        $tablet->recommendations()->detach();
        if ($syncData !== []) {
            $tablet->recommendations()->attach($syncData);
        }
    }

    private function createVersionSnapshot(Tablet $tablet): void
    {
        TabletConfigVersion::create([
            'tablet_id' => $tablet->id,
            'version_number' => $tablet->config_version,
            'featured_product_id' => $tablet->featured_product_id,
            'recommendation_product_ids' => $tablet->recommendations()->pluck('products.id')->all(),
        ]);
    }
}
