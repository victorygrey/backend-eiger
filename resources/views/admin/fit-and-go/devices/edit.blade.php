@extends('layouts.admin')

@section('title', 'Edit Perangkat: ' . $device->name)
@section('page-title', 'Konfigurasi Perangkat Kiosk')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.fit-and-go.index', ['tab' => 'devices']) }}">AI Fit & Go</a></li>
    <li class="breadcrumb-item active">{{ $device->name }}</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="mb-0 fw-bold">{{ $device->name }}</h4>
            <span class="badge bg-secondary font-monospace">{{ $device->device_code }}</span>
            @if($device->is_active)
                <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Aktif</span>
            @else
                <span class="badge bg-secondary">Nonaktif</span>
            @endif
        </div>
        <p class="text-muted small mb-0">
            Perbarui parameter jaringan, GPU Workstation endpoint, dan kamera live stream AI Fit & Go.
        </p>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('admin.fit-and-go.devices.ping', $device) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-success shadow-sm" title="Uji Koneksi Perangkat">
                <i class="bi bi-broadcast me-1"></i>Ping Status
            </button>
        </form>
        <a href="{{ route('admin.fit-and-go.index', ['tab' => 'devices']) }}" class="btn btn-outline-secondary">
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

<form method="POST" action="{{ route('admin.fit-and-go.devices.update', $device) }}">
    @csrf
    @method('PUT')
    @include('admin.fit-and-go.devices._form')
</form>
@endsection
