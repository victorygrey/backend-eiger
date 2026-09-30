@extends('layouts.admin')

@section('title', 'Atur Pemetaan RFID')
@section('page-title', 'Atur Pemetaan RFID')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.rfid-tags.index') }}" class="text-decoration-none">RFID Tags</a></li>
    <li class="breadcrumb-item active">Atur Pemetaan</li>
@endsection

@section('content')
<div class="mb-3"><a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a></div>
<div class="card">
    <div class="card-header d-flex align-items-center">
        <span class="d-inline-flex align-items-center justify-content-center rounded text-white me-2" style="width:34px;height:34px;background:linear-gradient(135deg,#f59e0b,#b45309)"><i class="bi bi-pencil-square-fill"></i></span>
        <div><div class="fw-semibold">Atur RFID <code>{{ $rfidTag->uid }}</code></div><small class="text-muted">Perbarui label fisik atau hubungkan tag dengan produk EIGER.</small></div>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.rfid-tags.update', $rfidTag) }}" method="POST">
            @csrf @method('PUT')
            @include('admin.rfid-tags._form-fields')
            <div class="mt-4 d-flex gap-2"><button class="btn btn-eiger"><i class="bi bi-save me-1"></i>Simpan Pemetaan</button><a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-secondary">Batal</a></div>
        </form>
    </div>
</div>
@endsection
