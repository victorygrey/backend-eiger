@extends('layouts.admin')

@section('title', 'AI Fit & Go Configuration')
@section('page-title', 'AI Fit & Go')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Konfigurasi</li>
    <li class="breadcrumb-item active">AI Fit & Go</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-person-bounding-box text-warning me-2"></i>Konfigurasi AI Fit & Go
        </h4>
        <p class="text-muted small mb-0">
            Kelola Kiosk Hardware, GPU Workstation Endpoint, Aktivitas Petualangan, Kategori & Visibilitas Katalog AI Fit & Go.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @if($currentTab === 'devices')
            <a href="{{ route('admin.fit-and-go.devices.create') }}" class="btn btn-eiger fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i>Tambah Perangkat Kiosk
            </a>
        @elseif($currentTab === 'activities')
            <a href="{{ route('admin.fit-and-go.activities.create') }}" class="btn btn-eiger fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i>Tambah Aktivitas
            </a>
        @endif
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

{{-- Navigation Tabs --}}
<ul class="nav nav-tabs border-bottom mb-4" id="fitGoTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a href="{{ route('admin.fit-and-go.index', ['tab' => 'devices']) }}"
           class="nav-link fw-semibold {{ $currentTab === 'devices' ? 'active text-primary' : 'text-muted' }}">
            <i class="bi bi-display me-1"></i>Perangkat Kiosk ({{ $devices->count() }})
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="{{ route('admin.fit-and-go.index', ['tab' => 'activities']) }}"
           class="nav-link fw-semibold {{ $currentTab === 'activities' ? 'active text-primary' : 'text-muted' }}">
            <i class="bi bi-compass me-1"></i>Aktivitas EIGER ({{ $activities->count() }})
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="{{ route('admin.fit-and-go.index', ['tab' => 'categories', 'category' => $selectedCategory ? $selectedCategory->code : 'hat']) }}"
           class="nav-link fw-semibold {{ $currentTab === 'categories' ? 'active text-primary' : 'text-muted' }}">
            <i class="bi bi-tags me-1"></i>Kategori & Visibilitas Produk
        </a>
    </li>
</ul>

{{-- TAB 1: DEVICES CONFIGURATION --}}
@if($currentTab === 'devices')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-cpu me-2"></i>Daftar Perangkat Kiosk & GPU Workstation</h6>
        <span class="badge bg-secondary">{{ $devices->count() }} Terdaftar</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Nama & Kode Kiosk</th>
                    <th>Lokasi</th>
                    <th>IP & GPU Endpoint</th>
                    <th>Status</th>
                    <th>Heartbeat Terakhir</th>
                    <th class="text-end" style="width: 180px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($devices as $device)
                <tr>
                    <td class="text-muted small">#{{ $device->id }}</td>
                    <td>
                        <div class="fw-bold text-dark">{{ $device->name }}</div>
                        <div class="text-muted small font-monospace"><i class="bi bi-qr-code me-1"></i>{{ $device->device_code }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-geo-alt me-1"></i>{{ $device->location ?: 'Belum diatur' }}
                        </span>
                    </td>
                    <td>
                        <div class="small">
                            <i class="bi bi-hdd-network text-muted me-1"></i>{{ $device->ip_address ?: '-' }}
                        </div>
                        <div class="small text-muted font-monospace text-truncate" style="max-width: 280px;" title="{{ $device->gpu_endpoint }}">
                            <i class="bi bi-lightning-charge-fill text-warning me-1"></i>{{ $device->gpu_endpoint ?: 'Default Endpoint' }}
                        </div>
                    </td>
                    <td>
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
                    </td>
                    <td class="small text-muted">
                        {{ $device->last_heartbeat_at ? $device->last_heartbeat_at->diffForHumans() : 'Belum ada data' }}
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <form action="{{ route('admin.fit-and-go.devices.ping', $device) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary" title="Ping Device">
                                    <i class="bi bi-activity"></i>
                                </button>
                            </form>
                            <a href="{{ route('admin.fit-and-go.devices.edit', $device) }}" class="btn btn-outline-primary" title="Edit Konfigurasi">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDeviceModal{{ $device->id }}" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>

                {{-- Delete Device Modal --}}
                <div class="modal fade" id="deleteDeviceModal{{ $device->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('admin.fit-and-go.devices.destroy', $device) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Apakah Anda yakin ingin menghapus konfigurasi perangkat <strong>{{ $device->name }}</strong> (<code>{{ $device->device_code }}</code>)?
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-danger fw-bold">Hapus Perangkat</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-display fs-1 d-block mb-2 text-secondary"></i>
                        Belum ada perangkat Kiosk yang terdaftar.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- TAB 2: ACTIVITIES (EIGER OUTDOOR ACTIVITIES) --}}
@if($currentTab === 'activities')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-compass me-2"></i>Daftar Aktivitas EIGER (SRS Halaman 10)</h6>
            <small class="text-muted">Aktivitas diambil dari Care MC Level 2 dan masing-masing memiliki foto representatif.</small>
        </div>
        <span class="badge bg-secondary">{{ $activities->count() }} Aktivitas</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px;">Urutan</th>
                    <th style="width: 100px;">Gambar</th>
                    <th>Nama Aktivitas</th>
                    <th>Care MC Level 2</th>
                    <th>Deskripsi</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $activity)
                <tr>
                    <td class="text-center font-monospace fw-bold">{{ $activity->sort_order }}</td>
                    <td>
                        @if($activity->image)
                            <img src="{{ $activity->image }}" alt="{{ $activity->name }}" class="rounded object-fit-cover shadow-sm" style="width: 60px; height: 60px;">
                        @else
                            <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border" style="width: 60px; height: 60px;">
                                <i class="bi bi-image"></i>
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="fw-bold text-dark">{{ $activity->name }}</div>
                        <div class="small text-muted font-monospace">{{ $activity->slug }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-diagram-2 me-1"></i>{{ $activity->care_mc_level_2 ?: '-' }}
                        </span>
                    </td>
                    <td class="small text-muted" style="max-width: 250px;">
                        {{ Str::limit($activity->description, 70) ?: '-' }}
                    </td>
                    <td>
                        @if($activity->is_active)
                            <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                        @else
                            <span class="badge bg-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('admin.fit-and-go.activities.edit', $activity) }}" class="btn btn-outline-primary" title="Edit Aktivitas">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteActivityModal{{ $activity->id }}" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>

                {{-- Delete Activity Modal --}}
                <div class="modal fade" id="deleteActivityModal{{ $activity->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('admin.fit-and-go.activities.destroy', $activity) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Apakah Anda yakin ingin menghapus aktivitas <strong>{{ $activity->name }}</strong>?
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-danger fw-bold">Hapus Aktivitas</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-compass fs-1 d-block mb-2 text-secondary"></i>
                        Belum ada aktivitas yang terdaftar.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- TAB 3: CATEGORIES & PRODUCT VISIBILITY --}}
@if($currentTab === 'categories')
<div class="row g-4 mb-4">
    {{-- Category Cards --}}
    @foreach($categories as $category)
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 {{ $selectedCategory && $selectedCategory->code === $category->code ? 'border border-primary shadow' : '' }}">
            <div class="card-body">
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
                <a href="{{ route('admin.fit-and-go.index', ['tab' => 'categories', 'category' => $category->code]) }}"
                   class="btn btn-sm w-100 {{ $selectedCategory && $selectedCategory->code === $category->code ? 'btn-primary fw-bold' : 'btn-outline-secondary' }}">
                    <i class="bi bi-eye me-1"></i>Kelola Visibilitas Produk
                </a>
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
                        <h5 class="modal-title fw-bold">Konfigurasi Kategori: {{ $category->display_name }}</h5>
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

{{-- Product Visibility Table for Selected Category --}}
@if($selectedCategory)
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-eye me-2"></i>Visibilitas Produk Kategori: <span class="text-primary">{{ $selectedCategory->display_name }}</span> (SRS FR-CMS-03)
            </h6>
            <small class="text-muted">Staff toko dapat mengatur item yang ditampilkan atau disembunyikan pada layar kiosk AI Fit & Go.</small>
        </div>
        <span class="badge bg-secondary">{{ $categoryProducts->count() }} Produk Ditemukan</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px;">Foto</th>
                    <th>SKU & Nama Produk</th>
                    <th>Kategori Master</th>
                    <th>Harga & Stok</th>
                    <th class="text-center" style="width: 200px;">Tampilkan di Kiosk?</th>
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
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="category_code" value="{{ $selectedCategory->code }}">
                            <input type="hidden" name="is_visible" value="{{ $product->is_fit_visible ? 0 : 1 }}">
                            <button type="submit" class="btn btn-sm {{ $product->is_fit_visible ? 'btn-success' : 'btn-outline-secondary' }} px-3">
                                @if($product->is_fit_visible)
                                    <i class="bi bi-eye-fill me-1"></i>Tampil
                                @else
                                    <i class="bi bi-eye-slash-fill me-1"></i>Sembunyi
                                @endif
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        Tidak ada produk yang cocok dengan kata kunci kategori ini (<code>{{ $selectedCategory->mc_keywords }}</code>).
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
