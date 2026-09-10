@extends('layouts.admin')

@section('title', 'Edit Produk')
@section('page-title', 'Edit Produk')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}" class="text-decoration-none">Products</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <div class="p-2 rounded text-white me-2" style="background:linear-gradient(135deg,#f59e0b,#b45309)">
            <i class="bi bi-pencil-square-fill"></i>
        </div>
        <span>Edit Produk — <code>{{ $product->sku }}</code></span>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.products.update', $product) }}" method="POST">
            @csrf
            @include('admin.products._pim')
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">SKU <span class="text-danger">*</span></label>
                    <input type="text" name="sku" value="{{ old('sku', $product->sku) }}"
                        class="form-control @error('sku') is-invalid @enderror" required>
                    @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nama Produk <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $product->name) }}"
                        class="form-control @error('name') is-invalid @enderror" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Harga (Rp)</label>
                    <input type="number" name="price" value="{{ old('price', $product->price) }}" step="0.01" min="0"
                        class="form-control @error('price') is-invalid @enderror">
                    @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Stok</label>
                    <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" min="0"
                        class="form-control @error('stock') is-invalid @enderror">
                    @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Zone</label>
                    <select name="zone_id" class="form-select @error('zone_id') is-invalid @enderror">
                        <option value="">-- Pilih Zone --</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $product->zone_id) == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">URL Gambar</label>
                    <input type="text" name="image" value="{{ old('image', $product->image) }}"
                        class="form-control @error('image') is-invalid @enderror">
                    @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Material</label>
                    <input type="text" name="material" value="{{ old('material', $product->material) }}"
                        class="form-control @error('material') is-invalid @enderror">
                    @error('material') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" rows="4"
                        class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_featured" value="1" id="is_featured"
                            class="form-check-input" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }}>
                        <label for="is_featured" class="form-check-label">Tandai sebagai Featured</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_discontinued" value="1" id="is_discontinued"
                            class="form-check-input" {{ old('is_discontinued', $product->is_discontinued) ? 'checked' : '' }}>
                        <label for="is_discontinued" class="form-check-label">Produk Discontinued</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-eiger">
                    <i class="bi bi-save me-1"></i>Simpan Perubahan
                </button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
