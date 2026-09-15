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
        $tags = RfidTag::with('product')->when($request->filled('search'), function ($q) use ($request) {
                $term = "%{$request->search}%";
                $q->where(function ($sub) use ($term) {
                    $sub->where('uid', 'like', $term)
                        ->orWhere('name', 'like', $term);
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.rfid-tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.rfid-tags.create');
    }

    public function store(StoreRfidTagRequest $request)
    {
        RfidTag::create($request->validated());

        return redirect()->route('admin.rfid-tags.index')
            ->with('success', 'RFID Tag berhasil ditambahkan.');
    }

    public function edit(RfidTag $rfidTag)
    {
        $products = Product::orderBy('name')->get();
        return view('admin.rfid-tags.edit', compact('rfidTag', 'products'));
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
}
