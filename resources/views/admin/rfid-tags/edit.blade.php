@extends('layouts.admin')

@section('title', 'Edit RFID Tag')
@section('page-title', 'Edit RFID Tag')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.rfid-tags.index') }}" class="text-decoration-none">RFID Tags</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <div class="p-2 rounded text-white me-2" style="background:linear-gradient(135deg,#f59e0b,#b45309)">
            <i class="bi bi-pencil-square-fill"></i>
        </div>
        <span>Edit RFID Tag — <code>{{ $rfidTag->uid }}</code></span>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.rfid-tags.update', $rfidTag) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">UID <span class="text-danger">*</span></label>
                    <input type="text" name="uid" value="{{ old('uid', $rfidTag->uid) }}"
                        class="form-control font-monospace @error('uid') is-invalid @enderror" required>
                    @error('uid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nama / Label Tag <span class="text-muted">(opsional)</span></label>
                    <input type="text" name="name" value="{{ old('name', $rfidTag->name) }}"
                        class="form-control @error('name') is-invalid @enderror"
                        placeholder="Contoh: RFID Jaket Gunung Setiabudi">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label">Produk <span class="text-muted">(opsional)</span></label>
                    <select name="product_id" class="form-select @error('product_id') is-invalid @enderror">
                        <option value="">-- Belum dipetakan --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ old('product_id', $rfidTag->product_id) == $product->id ? 'selected' : '' }}>
                                {{ $product->sku }} — {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-eiger"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                <a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
