@extends('layouts.admin')

@section('title', 'Tambah Aktivitas AI Fit & Go')
@section('page-title', 'Tambah Aktivitas')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.fit-and-go.index', ['tab' => 'activities']) }}">AI Fit & Go</a></li>
    <li class="breadcrumb-item active">Tambah Aktivitas</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-compass text-warning me-2"></i>Tambah Aktivitas Outdoor
        </h4>
        <p class="text-muted small mb-0">
            Daftarkan persona aktivitas petualangan (Mountaineering, Riding, Hiking, dll) untuk rekomendasi produk AI Fit & Go dan scene ambience.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.fit-and-go.index', ['tab' => 'activities']) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Flash Messages --}}
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-x-circle-fill fs-5 me-2 flex-shrink-0"></i>
        <div>{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="POST" action="{{ route('admin.fit-and-go.activities.store') }}">
    @csrf
    @include('admin.fit-and-go.activities._form')
</form>
@endsection
