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
            @if($device->status === 'online' || $device->status === 'active')
                <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Online</span>
            @elseif($device->status === 'maintenance')
                <span class="badge badge-warning-soft"><i class="bi bi-wrench"></i> Maintenance</span>
            @else
                <span class="badge badge-danger-soft"><i class="bi bi-slash-circle-fill"></i> Offline</span>
            @endif
            @if(!$device->is_active)
                <span class="badge bg-secondary ms-1">Nonaktif</span>
            @endif
        </div>
        <p class="text-muted small mb-0">
            Kelola parameter hardware jaringan dan kontrol katalog visibilitas produk khusus untuk kiosk ini.
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

{{-- Sub-Navigation Pills for Kiosk Device --}}
<ul class="nav nav-pills mb-4 border-bottom pb-3">
    <li class="nav-item">
        <a href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'device']) }}"
           class="nav-link fw-semibold {{ $currentTab === 'device' ? 'active' : 'text-dark' }}">
            <i class="bi bi-sliders me-1"></i>Parameter Hardware & Jaringan
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'catalog', 'category' => $selectedCategory ? $selectedCategory->code : 'hat']) }}"
           class="nav-link fw-semibold {{ $currentTab === 'catalog' ? 'active' : 'text-dark' }}">
            <i class="bi bi-tags me-1"></i>Kategori & Visibilitas Produk Kiosk
        </a>
    </li>
</ul>

{{-- SUB-TAB 1: HARDWARE & NETWORK CONFIGURATION --}}
@if($currentTab === 'device')
<form method="POST" action="{{ route('admin.fit-and-go.devices.update', $device) }}">
    @csrf
    @method('PUT')
    @include('admin.fit-and-go.devices._form')
</form>
@endif

{{-- SUB-TAB 2: CATEGORY & PRODUCT VISIBILITY FOR THIS KIOSK --}}
@if($currentTab === 'catalog')
<div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4">
    <i class="bi bi-info-circle-fill fs-4 text-info me-3 flex-shrink-0"></i>
    <div>
        <strong>Kontrol Visibilitas Produk Kiosk: {{ $device->name }} (<code>{{ $device->device_code }}</code>)</strong><br>
        <small class="text-muted">
            Perubahan visibilitas di halaman ini berlaku spesifik untuk unit kiosk ini. Pengunjung pada kiosk ini hanya akan melihat produk dengan status <strong>Tampil</strong>.
        </small>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Category Cards --}}
    @foreach($categories as $category)
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 {{ $selectedCategory && $selectedCategory->code === $category->code ? 'border border-primary shadow' : '' }}">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-bold mb-0 text-dark">{{ $category->display_name }}</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary p-1 py-0" data-bs-toggle="modal" data-bs-target="#editCatModal{{ $category->id }}" title="Edit Preset">
                        <i class="bi bi-gear"></i>
                    </button>
                </div>
                <div class="small text-muted mb-2 font-monospace">
                    Kode: <strong>{{ $category->code }}</strong> ({{ $category->mc_level }})
                </div>
                <div class="border rounded p-1 mb-2 bg-light text-center overflow-hidden" style="height: 100px;">
                    @if($category->background_image)
                        <img src="{{ $category->background_image }}" alt="{{ $category->display_name }}" class="img-fluid rounded h-100 object-fit-cover w-100">
                    @else
                        <div class="h-100 d-flex align-items-center justify-content-center text-muted small">
                            <i class="bi bi-image me-1"></i>No Background
                        </div>
                    @endif
                </div>
                <div class="small text-muted text-truncate mb-3" title="{{ $category->mc_keywords }}">
                    Kata Kunci: <em>{{ $category->mc_keywords }}</em>
                </div>
                <div class="mt-auto">
                    <a href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'catalog', 'category' => $category->code]) }}"
                       class="btn btn-sm w-100 {{ $selectedCategory && $selectedCategory->code === $category->code ? 'btn-primary fw-bold' : 'btn-outline-secondary' }}">
                        @if($selectedCategory && $selectedCategory->code === $category->code)
                            <i class="bi bi-check-circle-fill me-1"></i>Sedang Dipilih
                        @else
                            <i class="bi bi-eye me-1"></i>Pilih Kategori Ini
                        @endif
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Category Modal --}}
    <div class="modal fade" id="editCatModal{{ $category->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.fit-and-go.categories.update', $category) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Konfigurasi Preset Kategori: {{ $category->display_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Tampilan <span class="text-danger">*</span></label>
                            <input type="text" name="display_name" class="form-control" value="{{ old('display_name', $category->display_name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">URL Background Preset (SRS FR-CMS-03)</label>
                            <input type="url" name="background_image" class="form-control font-monospace" value="{{ old('background_image', $category->background_image) }}" placeholder="https://...">
                            <small class="text-muted">Gambar latar belakang saat pengunjung memilih kategori ini di Kiosk.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kata Kunci Filter Katalog (Pisahkan dengan koma)</label>
                            <input type="text" name="mc_keywords" class="form-control" value="{{ old('mc_keywords', $category->mc_keywords) }}" placeholder="topi, headwear, cap">
                            <small class="text-muted">Digunakan untuk mencocokkan produk dari master katalog.</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="catActiveSwitch{{ $category->id }}" {{ $category->is_active ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="catActiveSwitch{{ $category->id }}">Kategori Aktif di Kiosk</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-eiger fw-bold">Simpan Pengaturan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Product Visibility Table for Selected Category on this Kiosk Device --}}
@if($selectedCategory)
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-eye me-2 text-primary"></i>Visibilitas Produk Kategori: <span class="text-primary">{{ $selectedCategory->display_name }}</span>
            </h6>
            <small class="text-muted">
                Perangkat Kiosk: <strong>{{ $device->name }}</strong> (<code>{{ $device->device_code }}</code>)
            </small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary">{{ $categoryProducts->count() }} Produk Ditemukan</span>
            <form method="GET" action="{{ route('admin.fit-and-go.devices.edit', $device) }}" class="d-flex gap-1">
                <input type="hidden" name="tab" value="catalog">
                <input type="hidden" name="category" value="{{ $selectedCategory->code }}">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Cari nama / SKU..." value="{{ $searchQuery ?? '' }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    @if(!empty($searchQuery))
                        <a href="{{ route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'catalog', 'category' => $selectedCategory->code]) }}" class="btn btn-outline-danger">
                            <i class="bi bi-x"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px;">Foto</th>
                    <th>SKU & Nama Produk</th>
                    <th>Kategori Master</th>
                    <th>Harga & Stok</th>
                    <th class="text-center" style="width: 220px;">Tampilkan di Kiosk Ini?</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categoryProducts as $product)
                <tr>
                    <td>
                        @if($product->image_url)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="rounded object-fit-cover shadow-sm" style="width: 50px; height: 50px;">
                        @else
                            <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border" style="width: 50px; height: 50px;">
                                <i class="bi bi-image"></i>
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="fw-bold text-dark">{{ $product->name }}</div>
                        <div class="small text-muted font-monospace"><i class="bi bi-upc me-1"></i>{{ $product->sku }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $product->category ?: '-' }}</span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}</div>
                        <div class="small text-muted">Stok: <span class="fw-semibold">{{ $product->stock ?? 0 }}</span> unit</div>
                    </td>
                    <td class="text-center">
                        <form action="{{ route('admin.fit-and-go.items.visibility') }}" method="POST">
                            @csrf
                            <input type="hidden" name="device_id" value="{{ $device->id }}">
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="category_code" value="{{ $selectedCategory->code }}">
                            <input type="hidden" name="is_visible" value="{{ $product->is_fit_visible ? 0 : 1 }}">
                            <button type="submit" class="btn btn-sm {{ $product->is_fit_visible ? 'btn-success' : 'btn-outline-secondary' }} px-3">
                                @if($product->is_fit_visible)
                                    <i class="bi bi-eye-fill me-1"></i>Tampil di Kiosk
                                @else
                                    <i class="bi bi-eye-slash-fill me-1"></i>Sembunyi di Kiosk
                                @endif
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        @if(!empty($searchQuery))
                            Tidak ada produk yang cocok dengan pencarian "<strong>{{ $searchQuery }}</strong>".
                        @else
                            Tidak ada produk yang cocok dengan kata kunci kategori ini (<code>{{ $selectedCategory->mc_keywords }}</code>).
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endif

@endsection
