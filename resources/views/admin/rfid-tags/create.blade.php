@extends('layouts.admin')

@section('title', 'Daftarkan RFID Tag')
@section('page-title', 'Daftarkan RFID Tag')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.rfid-tags.index') }}" class="text-decoration-none">RFID Tags</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="mb-3"><a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a></div>
<div class="card">
    <div class="card-header d-flex align-items-center">
        <span class="d-inline-flex align-items-center justify-content-center rounded text-white me-2" style="width:34px;height:34px;background:linear-gradient(135deg,#f59e0b,#b45309)"><i class="bi bi-plus-square-fill"></i></span>
        <div><div class="fw-semibold">Daftarkan RFID Baru</div><small class="text-muted">Simpan UID fisik dan hubungkan dengan SKU produk bila sudah diketahui.</small></div>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.rfid-tags.store') }}" method="POST">
            @csrf
            @include('admin.rfid-tags._form-fields', ['rfidTag' => null])
            <div class="mt-4 d-flex gap-2"><button class="btn btn-eiger"><i class="bi bi-save me-1"></i>Simpan RFID</button><a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-secondary">Batal</a></div>
        </form>
    </div>
</div>
@endsection
