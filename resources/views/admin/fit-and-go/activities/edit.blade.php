@extends('layouts.admin')

@section('title', 'Edit Aktivitas: ' . $activity->name)
@section('page-title', 'Konfigurasi Aktivitas')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.fit-and-go.index', ['tab' => 'activities']) }}">AI Fit & Go</a></li>
    <li class="breadcrumb-item active">{{ $activity->name }}</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="mb-0 fw-bold">{{ $activity->name }}</h4>
            <span class="badge bg-secondary font-monospace">{{ $activity->slug }}</span>
            @if($activity->is_active)
                <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Aktif</span>
            @else
                <span class="badge bg-secondary">Nonaktif</span>
            @endif
        </div>
        <p class="text-muted small mb-0">
            Perbarui nama aktivitas, pemetaan kategori CARE MC Level 2, dan visual pendukung.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.fit-and-go.index', ['tab' => 'activities']) }}" class="btn btn-outline-secondary">
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

<form method="POST" action="{{ route('admin.fit-and-go.activities.update', $activity) }}">
    @csrf
    @method('PUT')
    @include('admin.fit-and-go.activities._form')
</form>
@endsection
