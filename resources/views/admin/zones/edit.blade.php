@extends('layouts.admin')

@section('title', 'Edit Zone')
@section('page-title', 'Edit Zone')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.zones.index') }}" class="text-decoration-none">Zones</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.zones.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <div class="p-2 rounded text-white me-2" style="background:linear-gradient(135deg,#f59e0b,#b45309)">
            <i class="bi bi-pencil-square-fill"></i>
        </div>
        <span>Edit Zone — {{ $zone->name }}</span>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.zones.update', $zone) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Nama Zone <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $zone->name) }}"
                    class="form-control @error('name') is-invalid @enderror" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" rows="4"
                    class="form-control @error('description') is-invalid @enderror">{{ old('description', $zone->description) }}</textarea>
                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-eiger"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                <a href="{{ route('admin.zones.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
