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
            <a href="{{ route('admin.led-ambience.rfid-items.create') }}" class="btn btn-eiger fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i>Tambah Mapping RFID
            </a>
        @else
            <a href="{{ route('admin.led-ambience.scenes.create') }}" class="btn btn-eiger fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i>Tambah Scene Ambience
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
                        @if($item->rfidTag && $item->rfidTag->name)
                            <div class="fw-semibold text-primary small d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-tag-fill"></i> {{ $item->rfidTag->name }}
                            </div>
                        @endif
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
                            <a href="{{ route('admin.led-ambience.rfid-items.edit', $item) }}" class="btn btn-outline-primary" title="Edit Mapping">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteRfidModal{{ $item->id }}" title="Hapus Mapping">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </td>
                </tr>

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
                        <a href="{{ route('admin.led-ambience.scenes.edit', $scene) }}" class="btn btn-outline-primary" title="Edit Scene">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </a>
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
@endif

@endsection

