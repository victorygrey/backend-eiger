@extends('layouts.admin')

@section('title', 'Edit Mapping RFID: ' . $item->rfid_tag)
@section('page-title', 'Konfigurasi Mapping RFID')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.led-ambience.index', ['tab' => 'rfid']) }}">LED Ambience</a></li>
    <li class="breadcrumb-item active">{{ $item->rfid_tag }}</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="mb-0 fw-bold">{{ $item->product?->name ?? 'Mapping RFID LED Ambience' }}</h4>
            <span class="badge bg-secondary font-monospace">{{ $item->rfid_tag }}</span>
            @if($item->is_active)
                <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Aktif</span>
            @else
                <span class="badge bg-secondary">Nonaktif</span>
            @endif
        </div>
        <p class="text-muted small mb-0">
            Perbarui asosiasi produk, scene ambience spesifik, atau aktivitas outdoor pemicu suasana.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.led-ambience.index', ['tab' => 'rfid']) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Flash Messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5 me-2 flex-shrink-0"></i>
        <div>{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-x-circle-fill fs-5 me-2 flex-shrink-0"></i>
        <div>{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="POST" action="{{ route('admin.led-ambience.rfid-items.update', $item) }}">
    @csrf
    @method('PUT')
    @include('admin.led-ambience.rfid-items._form')
</form>
@endsection
