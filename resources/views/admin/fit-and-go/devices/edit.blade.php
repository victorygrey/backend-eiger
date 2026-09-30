@extends('layouts.admin')

@section('title', 'Konfigurasi Kiosk: '.$device->name)
@section('page-title', 'Konfigurasi Kiosk')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.fit-and-go.index', ['tab' => 'kiosks']) }}">AI Fit &amp; Go</a></li>
    <li class="breadcrumb-item active">{{ $device->name }}</li>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="mb-0 fw-bold">{{ $device->name }}</h4>
            <span class="badge bg-secondary font-monospace">{{ $device->device_code }}</span>
        </div>
        <p class="text-muted mb-0">Atur identitas, lokasi, dan keamanan aktivasi perangkat kiosk.</p>
    </div>
    <a href="{{ route('admin.fit-and-go.index', ['tab' => 'kiosks']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<form method="POST" action="{{ route('admin.fit-and-go.devices.update', $device) }}">
    @csrf @method('PUT')
    @include('admin.fit-and-go.devices._form')
</form>
@endsection
