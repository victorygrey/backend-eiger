@extends('layouts.admin')

@section('title', 'Tambah Mapping RFID Table Expedition')
@section('page-title', 'Tambah Mapping Table Expedition')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.table-expedition.index') }}">Table Expedition</a></li>
    <li class="breadcrumb-item active">Tambah Mapping</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-compass-fill text-warning me-2"></i>Tambah Mapping RFID Table Expedition
        </h4>
        <p class="text-muted small mb-0">
            Hubungkan tag RFID produk EIGER dengan detail spesifikasi teknis, video demo, dan ringkasan AI untuk meja interaktif.
        </p>
    </div>
    <a href="{{ route('admin.table-expedition.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

{{-- Flash Messages --}}
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-x-circle-fill fs-5 me-2 flex-shrink-0"></i>
        <div>{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="POST" action="{{ route('admin.table-expedition.store') }}">
    @csrf
    @include('admin.table-expedition._form')
</form>
@endsection
