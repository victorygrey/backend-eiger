@extends('layouts.admin')

@section('title', 'Edit Scene: ' . $scene->name)
@section('page-title', 'Konfigurasi Scene Ambience')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.led-ambience.index', ['tab' => 'scenes']) }}">LED Ambience</a></li>
    <li class="breadcrumb-item active">{{ $scene->name }}</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="rounded-circle d-inline-block shadow-sm" style="width: 18px; height: 18px; background-color: {{ $scene->lighting_color }};"></span>
            <h4 class="mb-0 fw-bold">{{ $scene->name }}</h4>
            <span class="badge bg-secondary font-monospace">{{ strtoupper($scene->scene_type) }}</span>
            @if($scene->is_active)
                <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Aktif</span>
            @else
                <span class="badge bg-secondary">Nonaktif</span>
            @endif
        </div>
        <p class="text-muted small mb-0">
            Perbarui streaming video, audio loop, warna lampu RGB, atau aktivitas terkait untuk scene ini.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.led-ambience.index', ['tab' => 'scenes']) }}" class="btn btn-outline-secondary">
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

<form method="POST" action="{{ route('admin.led-ambience.scenes.update', $scene) }}">
    @csrf
    @method('PUT')
    @include('admin.led-ambience.scenes._form')
</form>
@endsection
