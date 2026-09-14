@extends('layouts.admin')

@section('title', 'Tambah Scene Ambience Baru')
@section('page-title', 'Tambah Scene Ambience')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.led-ambience.index', ['tab' => 'scenes']) }}">LED Ambience</a></li>
    <li class="breadcrumb-item active">Tambah Scene</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-film text-warning me-2"></i>Tambah Preset Scene Ambience Baru
        </h4>
        <p class="text-muted small mb-0">
            Atur kombinasi video lanskap layar lebar, efek soundscape audio dinamis, dan warna pencahayaan LED toko.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.led-ambience.index', ['tab' => 'scenes']) }}" class="btn btn-outline-secondary">
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

<form method="POST" action="{{ route('admin.led-ambience.scenes.store') }}">
    @csrf
    @include('admin.led-ambience.scenes._form')
</form>
@endsection
