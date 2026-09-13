@extends('layouts.admin')

@section('title', 'Tambah RFID Tag')
@section('page-title', 'Tambah RFID Tag')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.rfid-tags.index') }}" class="text-decoration-none">RFID Tags</a></li>
    <li class="breadcrumb-item active">Tambah</li>
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
            <i class="bi bi-plus-square-fill"></i>
        </div>
        <span>Form RFID Tag Baru</span>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.rfid-tags.store') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">UID RFID <span class="text-danger">*</span></label>
                    <input type="text" name="uid" value="{{ old('uid') }}"
                        class="form-control font-monospace @error('uid') is-invalid @enderror"
                        placeholder="Contoh: E280116060000204..." required autofocus>
                    <div class="form-text text-muted">
                        Masukkan kode UID / EPC dari tag RFID. Simbol pemisah seperti strip (<code>-</code>) atau spasi akan otomatis dibersihkan.
                    </div>
                    @error('uid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-eiger"><i class="bi bi-save me-1"></i>Simpan</button>
                <a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
