@extends('layouts.admin')

@section('title', 'Konfigurasi Kiosk: ' . $device->name)
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
            <span class="badge {{ in_array($device->status, ['online', 'active']) ? 'badge-success-soft' : ($device->status === 'maintenance' ? 'badge-warning-soft' : 'badge-danger-soft') }}">{{ ucfirst($device->status) }}</span>
        </div>
        <p class="text-muted small mb-0">Kelola identitas perangkat dan katalog produk yang tampil pada kiosk ini.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('admin.fit-and-go.devices.ping', $device) }}">@csrf
            <button type="submit" class="btn btn-outline-success shadow-sm"><i class="bi bi-broadcast me-1"></i>Ping Status</button>
        </form>
        <a href="{{ route('admin.fit-and-go.index', ['tab' => 'devices']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<ul class="nav nav-pills mb-4 border-bottom pb-3 gap-1">
    <li class="nav-item"><a href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'device']) }}" class="nav-link fw-semibold {{ $currentTab === 'device' ? 'active' : 'text-dark' }}"><i class="bi bi-sliders me-1"></i>Identitas Perangkat</a></li>
    <li class="nav-item"><a href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'catalog', 'category' => $selectedCategory?->code]) }}" class="nav-link fw-semibold {{ $currentTab === 'catalog' ? 'active' : 'text-dark' }}"><i class="bi bi-tags me-1"></i>Kategori & Visibilitas Produk Kiosk</a></li>
    <li class="nav-item"><a href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'activities', 'activity' => $selectedActivity?->slug]) }}" class="nav-link fw-semibold {{ $currentTab === 'activities' ? 'active' : 'text-dark' }}"><i class="bi bi-compass me-1"></i>EIGER Activity</a></li>
</ul>

@if($currentTab === 'device')
    <form method="POST" action="{{ route('admin.fit-and-go.devices.update', $device) }}">@csrf @method('PUT') @include('admin.fit-and-go.devices._form')</form>
@elseif($currentTab === 'catalog')
    <div class="alert alert-info border-0 shadow-sm"><i class="bi bi-info-circle-fill me-2"></i>Pilih produk secara manual untuk setiap kategori. Katalog kandidat sudah disaring berdasarkan jenis produk PIM.</div>
    <div class="row g-3 mb-4">
        @foreach($categories as $category)
            <div class="col-6 col-lg-3">
                <a class="card h-100 text-decoration-none shadow-sm {{ $selectedCategory?->id === $category->id ? 'border-primary' : 'border-0' }}" href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'catalog', 'category' => $category->code]) }}">
                    @if($category->background_image)<img src="{{ $category->background_image }}" class="card-img-top object-fit-cover" style="height:100px" alt="{{ $category->display_name }}">@endif
                    <div class="card-body py-3"><div class="fw-bold text-dark">{{ $category->display_name }}</div><small class="text-muted">{{ $category->mc_level }}</small></div>
                </a>
            </div>
        @endforeach
    </div>
    @if($selectedCategory)
        @include('admin.fit-and-go.devices._product-picker', ['pickerId' => 'category-picker', 'pickerTitle' => 'Produk ' . $selectedCategory->display_name, 'pickerDescription' => 'Pilih maksimal 30 produk yang akan tampil pada kategori ini di ' . $device->name . '.', 'pickerProducts' => $categoryProducts, 'selectedProducts' => $selectedCategoryProducts, 'formAction' => route('admin.fit-and-go.devices.catalog.sync', [$device, $selectedCategory]), 'saveLabel' => 'Simpan Produk Kategori'])
    @endif
@elseif($currentTab === 'activities')
    <div class="alert alert-info border-0 shadow-sm"><i class="bi bi-info-circle-fill me-2"></i>Atur rekomendasi produk berdasarkan aktivitas secara terpisah untuk kiosk <strong>{{ $device->name }}</strong>.</div>
    <div class="d-flex flex-wrap gap-2 mb-4">
        @foreach($activities as $activity)
            <a href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'activities', 'activity' => $activity->slug]) }}" class="btn {{ $selectedActivity?->id === $activity->id ? 'btn-primary' : 'btn-outline-secondary' }}"><i class="bi bi-compass me-1"></i>{{ $activity->name }}</a>
        @endforeach
    </div>
    @if($selectedActivity)
        @include('admin.fit-and-go.devices._product-picker', ['pickerId' => 'activity-picker', 'pickerTitle' => 'Rekomendasi Aktivitas ' . $selectedActivity->name, 'pickerDescription' => 'Kandidat mengikuti custom attribute activity pada data PIM. Urutan kanan menjadi urutan rekomendasi.', 'pickerProducts' => $activityProducts, 'selectedProducts' => $selectedActivityProducts, 'formAction' => route('admin.fit-and-go.devices.activities.sync', [$device, $selectedActivity]), 'saveLabel' => 'Simpan Produk Aktivitas'])
    @endif
@endif
@endsection
