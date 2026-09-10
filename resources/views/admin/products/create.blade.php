@extends('layouts.admin')

@section('title', 'Tambah Produk')
@section('page-title', 'Tambah Produk')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}" class="text-decoration-none">Products</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <div class="p-2 rounded text-white me-2" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <i class="bi bi-plus-square-fill"></i>
        </div>
        <span>Form Produk Baru</span>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.products.store') }}" method="POST">
            @csrf
            @include('admin.products._pim')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">SKU <span class="text-danger">*</span></label>
                    <input type="text" name="sku" value="{{ old('sku') }}"
                        class="form-control @error('sku') is-invalid @enderror" placeholder="Contoh: EGR-001" required>
                    @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nama Produk <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                        class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Laptop Mountain Pro" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Harga (Rp)</label>
                    <input type="number" name="price" value="{{ old('price', 0) }}" step="0.01" min="0"
                        class="form-control @error('price') is-invalid @enderror">
                    @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Stok</label>
                    <input type="number" name="stock" value="{{ old('stock', 0) }}" min="0"
                        class="form-control @error('stock') is-invalid @enderror">
                    @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Zone</label>
                    <select name="zone_id" class="form-select @error('zone_id') is-invalid @enderror">
                        <option value="">-- Pilih Zone --</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">URL Gambar</label>
                    <input type="text" name="image" value="{{ old('image') }}"
                        class="form-control @error('image') is-invalid @enderror"
                        placeholder="https://...">
                    @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Material</label>
                    <input type="text" name="material" value="{{ old('material') }}"
                        class="form-control @error('material') is-invalid @enderror"
                        placeholder="Contoh: Ripstop Nylon">
                    @error('material') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" rows="4"
                        class="form-control @error('description') is-invalid @enderror"
                        placeholder="Deskripsi produk...">{{ old('description') }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_featured" value="1" id="is_featured"
                            class="form-check-input" {{ old('is_featured') ? 'checked' : '' }}>
                        <label for="is_featured" class="form-check-label">Tandai sebagai Featured</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_discontinued" value="1" id="is_discontinued"
                            class="form-check-input" {{ old('is_discontinued') ? 'checked' : '' }}>
                        <label for="is_discontinued" class="form-check-label">Produk Discontinued</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-eiger">
                    <i class="bi bi-save me-1"></i>Simpan
                </button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
