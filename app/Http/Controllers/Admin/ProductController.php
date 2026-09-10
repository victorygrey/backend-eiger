<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Zone;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a paginated list of products with search and filtering.
     */
    public function index(Request $request)
    {
        $products = Product::with('zone')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('sku', 'like', "%{$request->search}%");
                });
            })
            ->when($request->filled('zone_id'), fn($q) => $q->where('zone_id', $request->zone_id))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $zones = Zone::all();

        return view('admin.products.index', compact('products', 'zones'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function pimLookup(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/']]);
        try { return response()->json(app(\App\Services\PimProductLookup::class)->get($data['code'])); }
        catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (\Throwable $e) { return response()->json(['message' => 'PIM tidak dapat dihubungi atau payload belum tersedia.'], 502); }
    }

    public function create()
    {
        $zones = Zone::all();
        return view('admin.products.create', compact('zones'));
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request)
    {
        $data = app(\App\Services\PimFormData::class)->apply($request->validated(), $request);
        $data['is_featured'] = $request->has('is_featured');
        $data['is_discontinued'] = $request->has('is_discontinued');

        Product::create($data);

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        $zones = Zone::all();
        return view('admin.products.edit', compact('product', 'zones'));
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = app(\App\Services\PimFormData::class)->apply($request->validated(), $request);
        $data['is_featured'] = $request->has('is_featured');
        $data['is_discontinued'] = $request->has('is_discontinued');

        $product->update($data);

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}
