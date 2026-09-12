@extends('layouts.admin')

@section('title', 'LED Ambience (Immersive Ambience Digital)')
@section('page-title', 'LED Ambience')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Konfigurasi</li>
    <li class="breadcrumb-item active">LED Ambience</li>
@endsection

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-soundwave text-warning me-2"></i>LED Ambience (Immersive Ambience Digital)
        </h4>
        <p class="text-muted small mb-0">
            Kelola konten video, audio dinamis, pencahayaan, dan pemetaan RFID untuk mengubah suasana toko secara otomatis saat produk diletakkan.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @if($currentTab === 'rfid')
            <button type="button" class="btn btn-eiger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addRfidModal">
                <i class="bi bi-plus-lg me-1"></i>Tambah Mapping RFID
            </button>
        @else
            <button type="button" class="btn btn-eiger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addSceneModal">
                <i class="bi bi-plus-lg me-1"></i>Tambah Scene Ambience
            </button>
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

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $rfidItems->count() }}</div>
                    <div class="stat-label">RFID Terpetakan</div>
                </div>
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-broadcast-pin"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $scenes->count() }}</div>
                    <div class="stat-label">Total Scene</div>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-film"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                @php($idleScene = $scenes->firstWhere('scene_type', 'idle'))
                <div>
                    <div class="stat-value text-success">{{ $idleScene ? 'Aktif' : 'Belum' }}</div>
                    <div class="stat-label">Idle Scene Loop</div>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-repeat"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-secondary">{{ $scenes->whereNotNull('activity_slug')->pluck('activity_slug')->unique()->count() }}</div>
                    <div class="stat-label">Aktivitas Terhubung</div>
                </div>
                <div class="stat-icon bg-secondary-subtle text-secondary">
                    <i class="bi bi-compass"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Navigation Tabs --}}
<ul class="nav nav-tabs border-bottom mb-4" id="ledTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a href="{{ route('admin.led-ambience.index', ['tab' => 'rfid']) }}"
           class="nav-link fw-semibold {{ $currentTab === 'rfid' ? 'active text-primary' : 'text-muted' }}">
            <i class="bi bi-broadcast-pin me-1"></i>Mapping RFID Produk ({{ $rfidItems->count() }})
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="{{ route('admin.led-ambience.index', ['tab' => 'scenes']) }}"
           class="nav-link fw-semibold {{ $currentTab === 'scenes' ? 'active text-primary' : 'text-muted' }}">
            <i class="bi bi-film me-1"></i>Konten Scene Ambience Video & Audio ({{ $scenes->count() }})
        </a>
    </li>
</ul>

{{-- TAB 1: RFID ITEM MAPPINGS (Can Add, Edit, Delete as requested) --}}
@if($currentTab === 'rfid')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-cpu me-2 text-primary"></i>Daftar Tag RFID Khusus LED Ambience</h6>
            <small class="text-muted">Konfigurasi RFID khusus LED Ambience — dapat ditambah, diedit, atau dihapus secara mandiri.</small>
        </div>
        <span class="badge bg-secondary">{{ $rfidItems->count() }} Tag Terdaftar</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Tag RFID (EPC)</th>
                    <th>Produk EIGER</th>
                    <th>Aktivitas Outdoor</th>
                    <th>Scene Ambience Yang Dipicu</th>
                    <th>Status</th>
                    <th>Terakhir Di-scan</th>
                    <th class="pe-3 text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rfidItems as $item)
                <tr>
                    <td class="ps-3">
                        <div class="font-monospace fw-bold text-dark">{{ $item->rfid_tag }}</div>
                        @if($item->notes)
                            <small class="text-muted">{{ $item->notes }}</small>
                        @endif
                    </td>
                    <td>
                        @if($item->product)
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $item->product->image ?: 'https://placehold.co/50x50?text=EIGER' }}"
                                     alt="{{ $item->product->name }}" class="rounded border object-fit-cover shadow-sm flex-shrink-0"
                                     style="width: 44px; height: 44px;">
                                <div>
                                    <div class="fw-bold text-dark text-truncate" style="max-width: 220px;" title="{{ $item->product->name }}">
                                        {{ $item->product->name }}
                                    </div>
                                    <div class="small text-muted font-monospace">{{ $item->product->sku }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-muted fst-italic">Produk tidak terhubung</span>
                        @endif
                    </td>
                    <td>
                        @if($item->activity_slug)
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-compass me-1"></i>{{ ucwords(str_replace('-', ' ', $item->activity_slug)) }}
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>
                        @if($item->scene)
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle d-inline-block shadow-sm" style="width: 14px; height: 14px; background-color: {{ $item->scene->lighting_color }};"></span>
                                <span class="fw-semibold text-dark small">{{ $item->scene->name }}</span>
                            </div>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Otomatis Mengikuti Aktivitas</span>
                        @endif
                    </td>
                    <td>
                        @if($item->is_active)
                            <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                        @else
                            <span class="badge badge-gray-soft">Nonaktif</span>
                        @endif
                    </td>
                    <td class="small text-muted">
                        {{ $item->last_scanned_at ? $item->last_scanned_at->diffForHumans() : 'Belum pernah' }}
                    </td>
                    <td class="pe-3 text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRfidModal{{ $item->id }}" title="Edit Mapping">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteRfidModal{{ $item->id }}" title="Hapus Mapping">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </td>
                </tr>

                {{-- Edit RFID Item Modal --}}
                <div class="modal fade" id="editRfidModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('admin.led-ambience.rfid-items.update', $item) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">Edit Mapping RFID LED Ambience</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Tag RFID (EPC) <span class="text-danger">*</span></label>
                                        <input type="text" name="rfid_tag" class="form-control font-monospace" value="{{ old('rfid_tag', $item->rfid_tag) }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Produk EIGER <span class="text-danger">*</span></label>
                                        <select name="product_id" class="form-select" required>
                                            @foreach($products as $prod)
                                                <option value="{{ $prod->id }}" @selected($prod->id === $item->product_id)>
                                                    {{ $prod->name }} ({{ $prod->sku }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Aktivitas Outdoor (EIGER Activity)</label>
                                        <select name="activity_slug" class="form-select">
                                            <option value="">-- Pilih Aktivitas (Opsional) --</option>
                                            @foreach($activities as $act)
                                                <option value="{{ $act->slug }}" @selected($act->slug === $item->activity_slug)>
                                                    {{ $act->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Scene Ambience Spesifik</label>
                                        <select name="scene_id" class="form-select">
                                            <option value="">-- Otomatis Pilih Berdasarkan Aktivitas --</option>
                                            @foreach($scenes as $sc)
                                                <option value="{{ $sc->id }}" @selected($sc->id === $item->scene_id)>
                                                    {{ $sc->name }} ({{ strtoupper($sc->scene_type) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Catatan</label>
                                        <input type="text" name="notes" class="form-control" value="{{ old('notes', $item->notes) }}" placeholder="Contoh: Jaket sample meja display 1">
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editActive{{ $item->id }}" {{ $item->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="editActive{{ $item->id }}">Tag Aktif</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-eiger fw-bold">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Delete RFID Modal --}}
                <div class="modal fade" id="deleteRfidModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('admin.led-ambience.rfid-items.destroy', $item) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus Mapping</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Apakah Anda yakin ingin menghapus mapping RFID <code>{{ $item->rfid_tag }}</code> untuk produk <strong>{{ $item->product?->name }}</strong> dari modul LED Ambience?
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-danger fw-bold">Hapus Tag</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-broadcast-pin fs-1 d-block mb-2 text-secondary"></i>
                        Belum ada pemetaan tag RFID untuk LED Ambience.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add RFID Modal --}}
<div class="modal fade" id="addRfidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.led-ambience.rfid-items.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Mapping RFID ke LED Ambience</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tag RFID (EPC) <span class="text-danger">*</span></label>
                        <input type="text" name="rfid_tag" class="form-control font-monospace" placeholder="E280116060000204..." required>
                        <small class="text-muted">Masukkan kode hex EPC tag RFID yang terpasang pada fisik produk.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Produk EIGER Terkait <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Pilih Produk --</option>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->name }} ({{ $prod->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Aktivitas Outdoor (EIGER Activity)</label>
                        <select name="activity_slug" class="form-select">
                            <option value="">-- Pilih Aktivitas (Opsional) --</option>
                            @foreach($activities as $act)
                                <option value="{{ $act->slug }}">{{ $act->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scene Ambience Spesifik</label>
                        <select name="scene_id" class="form-select">
                            <option value="">-- Otomatis Berdasarkan Aktivitas --</option>
                            @foreach($scenes as $sc)
                                <option value="{{ $sc->id }}">{{ $sc->name }} ({{ strtoupper($sc->scene_type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan</label>
                        <input type="text" name="notes" class="form-control" placeholder="Contoh: Jaket display rak depan">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="newRfidActive" checked>
                        <label class="form-check-label fw-semibold" for="newRfidActive">Tag Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-eiger fw-bold">Simpan Mapping RFID</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- TAB 2: AMBIENCE SCENES (Video, Audio, Lighting Presets) --}}
@if($currentTab === 'scenes')
<div class="row g-4 mb-4">
    @forelse($scenes as $scene)
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 h-100 {{ $scene->scene_type === 'idle' ? 'border border-success' : '' }}">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle d-inline-block shadow-sm" style="width: 16px; height: 16px; background-color: {{ $scene->lighting_color }};" title="Lighting: {{ $scene->lighting_color }}"></span>
                    <h6 class="mb-0 fw-bold text-dark text-truncate" title="{{ $scene->name }}">{{ $scene->name }}</h6>
                </div>
                @if($scene->scene_type === 'idle')
                    <span class="badge badge-success-soft"><i class="bi bi-repeat"></i> IDLE LOOP</span>
                @else
                    <span class="badge bg-light text-dark border">{{ strtoupper($scene->scene_type) }}</span>
                @endif
            </div>
            <div class="card-body">
                <div class="mb-2 small">
                    <span class="text-muted">Aktivitas:</span>
                    <strong class="text-dark">{{ $scene->activity_slug ? ucwords(str_replace('-', ' ', $scene->activity_slug)) : 'Semua Aktivitas' }}</strong>
                </div>
                <p class="small text-muted mb-3" style="min-height: 40px;">
                    {{ $scene->description ?: 'Tidak ada deskripsi tambahan untuk scene ini.' }}
                </p>

                <div class="p-2 rounded bg-light border mb-3 small">
                    <div class="text-truncate mb-1" title="{{ $scene->video_url }}">
                        <i class="bi bi-camera-video text-primary me-1"></i>Video:
                        <span class="text-muted font-monospace">{{ $scene->video_url ?: 'Belum diatur' }}</span>
                    </div>
                    <div class="text-truncate" title="{{ $scene->audio_url }}">
                        <i class="bi bi-volume-up text-warning me-1"></i>Audio:
                        <span class="text-muted font-monospace">{{ $scene->audio_url ?: 'Belum diatur' }}</span>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <span class="badge bg-secondary-subtle text-secondary">
                        <i class="bi bi-broadcast me-1"></i>{{ $scene->items_count }} RFID Terhubung
                    </span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSceneModal{{ $scene->id }}" title="Edit Scene">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </button>
                        @if($scene->scene_type !== 'idle')
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSceneModal{{ $scene->id }}" title="Hapus Scene">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Scene Modal --}}
    <div class="modal fade" id="editSceneModal{{ $scene->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.led-ambience.scenes.update', $scene) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Edit Scene Ambience: {{ $scene->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Scene Ambience <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $scene->name) }}" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Tipe Scene <span class="text-danger">*</span></label>
                                <select name="scene_type" class="form-select" required>
                                    <option value="active" {{ $scene->scene_type === 'active' ? 'selected' : '' }}>Active Scene</option>
                                    <option value="idle" {{ $scene->scene_type === 'idle' ? 'selected' : '' }}>Idle Loop (Standby)</option>
                                    <option value="default" {{ $scene->scene_type === 'default' ? 'selected' : '' }}>Default Scene</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Aktivitas Outdoor</label>
                                <select name="activity_slug" class="form-select">
                                    <option value="">-- Bebas (General) --</option>
                                    @foreach($activities as $act)
                                        <option value="{{ $act->slug }}" @selected($act->slug === $scene->activity_slug)>
                                            {{ $act->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">URL Video Layar Lebar (MP4/Stream)</label>
                            <input type="url" name="video_url" class="form-control font-monospace" value="{{ old('video_url', $scene->video_url) }}" placeholder="https://...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">URL Audio Suasana (MP3/WAV)</label>
                            <input type="url" name="audio_url" class="form-control font-monospace" value="{{ old('audio_url', $scene->audio_url) }}" placeholder="https://...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Warna Pencahayaan Ambience (Lighting Color)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="lighting_color" class="form-control form-control-color" value="{{ old('lighting_color', $scene->lighting_color) }}" title="Pilih warna">
                                <input type="text" class="form-control font-monospace small" value="{{ $scene->lighting_color }}" readonly style="max-width: 120px;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Deskripsi Suasana</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $scene->description) }}</textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Urutan Prioritas</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $scene->sort_order) }}" min="0">
                            </div>
                            <div class="col-6 d-flex align-items-center pt-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="scAct{{ $scene->id }}" {{ $scene->is_active ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="scAct{{ $scene->id }}">Scene Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-eiger fw-bold">Simpan Scene</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Delete Scene Modal --}}
    <div class="modal fade" id="deleteSceneModal{{ $scene->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.led-ambience.scenes.destroy', $scene) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Hapus Scene Ambience</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Apakah Anda yakin ingin menghapus scene <strong>{{ $scene->name }}</strong>?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger fw-bold">Hapus Scene</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-film fs-1 d-block mb-2 text-secondary"></i>
        Belum ada scene ambience yang terdaftar.
    </div>
    @endforelse
</div>

{{-- Add Scene Modal --}}
<div class="modal fade" id="addSceneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.led-ambience.scenes.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Scene Ambience Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Scene Ambience <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Badai Puncak Gunung" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Tipe Scene <span class="text-danger">*</span></label>
                            <select name="scene_type" class="form-select" required>
                                <option value="active" selected>Active Scene</option>
                                <option value="idle">Idle Loop (Standby)</option>
                                <option value="default">Default Scene</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Aktivitas Outdoor</label>
                            <select name="activity_slug" class="form-select">
                                <option value="">-- Bebas (General) --</option>
                                @foreach($activities as $act)
                                    <option value="{{ $act->slug }}">{{ $act->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL Video Layar Lebar (MP4/Stream)</label>
                        <input type="url" name="video_url" class="form-control font-monospace" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL Audio Suasana (MP3/WAV)</label>
                        <input type="url" name="audio_url" class="form-control font-monospace" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Warna Pencahayaan Ambience (Lighting Color)</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="lighting_color" class="form-control form-control-color" value="#e8500a" title="Pilih warna">
                            <span class="small text-muted">Contoh: Orange (#e8500a), Biru (#0284c7), Hijau (#16a34a)</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi Suasana</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Deskripsi video dan efek suara..."></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Urutan Prioritas</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-6 d-flex align-items-center pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="newScAct" checked>
                                <label class="form-check-label fw-semibold" for="newScAct">Scene Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-eiger fw-bold">Simpan Scene</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
