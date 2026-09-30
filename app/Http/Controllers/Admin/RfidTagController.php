<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRfidTagRequest;
use App\Http\Requests\UpdateRfidTagRequest;
use App\Models\Product;
use App\Models\RfidTag;
use Illuminate\Http\Request;

class RfidTagController extends Controller
{
    public function index(Request $request)
    {
        $tags = RfidTag::query()
            ->with('product')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = "%{$request->search}%";
                $q->where(function ($sub) use ($term) {
                    $sub->where('uid', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhereHas('product', function ($products) use ($term) {
                            $products->where('sku', 'like', $term)
                                ->orWhere('name', 'like', $term);
                        });
                });
            })
            ->when($request->input('mapping') === 'mapped', fn ($query) => $query->whereNotNull('product_id'))
            ->when($request->input('mapping') === 'unassigned', fn ($query) => $query->whereNull('product_id'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => RfidTag::count(),
            'mapped' => RfidTag::whereNotNull('product_id')->count(),
            'unassigned' => RfidTag::whereNull('product_id')->count(),
            'scanned' => RfidTag::whereNotNull('last_scanned_at')->count(),
        ];

        return view('admin.rfid-tags.index', compact('tags', 'summary'));
    }

    public function create()
    {
        return view('admin.rfid-tags.create', ['products' => $this->products()]);
    }

    public function store(StoreRfidTagRequest $request)
    {
        RfidTag::create($request->validated());

        return redirect()->route('admin.rfid-tags.index')
            ->with('success', 'RFID Tag berhasil ditambahkan.');
    }

    public function edit(RfidTag $rfidTag)
    {
        return view('admin.rfid-tags.edit', [
            'rfidTag' => $rfidTag->load('product'),
            'products' => $this->products(),
        ]);
    }

    public function update(UpdateRfidTagRequest $request, RfidTag $rfidTag)
    {
        $rfidTag->update($request->validated());

        return redirect()->route('admin.rfid-tags.index')
            ->with('success', 'RFID Tag berhasil diperbarui.');
    }

    public function destroy(RfidTag $rfidTag)
    {
        $rfidTag->delete();

        return redirect()->route('admin.rfid-tags.index')
            ->with('success', 'RFID Tag berhasil dihapus.');
    }

    private function products()
    {
        return Product::query()
            ->whereRaw('LENGTH(sku) = 9')
            ->where('is_discontinued', false)
            ->where(function ($query) {
                $query->whereDoesntHave('pimRecord')
                    ->orWhere('pim_catalog_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);
    }
}
