@extends('layouts.admin')

@section('title', 'Table Expedition Hub')
@section('page-title', 'Table Expedition')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Konfigurasi</li>
    <li class="breadcrumb-item active">Table Expedition</li>
@endsection

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-compass-fill text-warning me-2"></i>Table Expedition Hub (Product Knowledge)
        </h4>
        <p class="text-muted small mb-0">
            Kelola pemetaan RFID produk ke spesifikasi teknis, fitur, video eksplorasi, ringkasan AI, dan komparasi produk pada meja pintar.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#standbyConfigModal">
            <i class="bi bi-gear me-1"></i>Pengaturan Standby & Panduan
        </button>
        <a href="{{ route('admin.table-expedition.create') }}" class="btn btn-eiger fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Mapping RFID
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

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $items->count() }}</div>
                    <div class="stat-label">RFID Table Expedition</div>
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
                    <div class="stat-value text-success">{{ $items->where('is_active', true)->count() }}</div>
                    <div class="stat-label">Tag Aktif</div>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-warning">{{ $items->whereNotNull('ai_summary')->count() }}</div>
                    <div class="stat-label">Dengan Ringkasan AI</div>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-robot"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-secondary">{{ $mediaVideoCount }}</div>
                    <div class="stat-label">Dengan Video Demo</div>
                </div>
                <div class="stat-icon bg-secondary-subtle text-secondary">
                    <i class="bi bi-camera-video"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Main Table --}}
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-cpu me-2 text-primary"></i>Daftar Pemetaan RFID Table Expedition</h6>
            <small class="text-muted">Konfigurasi RFID khusus Table Expedition — dapat ditambah, diedit, atau dihapus secara mandiri.</small>
        </div>
        <span class="badge bg-secondary">{{ $items->count() }} Tag Terdaftar</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Tag RFID (EPC)</th>
                    <th>Produk EIGER</th>
                    <th>Peruntukan (Ideal For)</th>
                    <th>Fitur & Spek</th>
                    <th>AI Summary</th>
                    <th>Rekomendasi Serupa</th>
                    <th>Status</th>
                    <th class="pe-3 text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
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
                                    <div class="small text-muted font-monospace">{{ $item->product->sku }} | Rp {{ number_format($item->product->price ?? 0, 0, ',', '.') }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-muted fst-italic">Produk tidak terhubung</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border text-truncate" style="max-width: 180px;" title="{{ $item->ideal_for }}">
                            {{ $item->ideal_for ?: 'Umum / Harian' }}
                        </span>
                    </td>
                    <td>
                        <div class="small">
                            <i class="bi bi-check2-circle text-success me-1"></i>{{ count($item->product?->technologies ?: []) }} Fitur
                        </div>
                        <div class="small text-muted">
                            <i class="bi bi-tools text-secondary me-1"></i>{{ count($item->product?->specifications ?: []) }} Spesifikasi
                        </div>
                    </td>
                    <td>
                        @if($item->ai_summary)
                            <span class="badge badge-success-soft" title="{{ $item->ai_summary }}"><i class="bi bi-robot"></i> Siap</span>
                        @else
                            <span class="badge bg-light text-muted border">Default</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            {{ count($item->similar_product_ids ?: []) }} Produk
                        </span>
                    </td>
                    <td>
                        @if($item->is_active)
                            <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                        @else
                            <span class="badge badge-gray-soft">Nonaktif</span>
                        @endif
                    </td>
                    <td class="pe-3 text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('admin.table-expedition.edit', $item) }}" class="btn btn-outline-primary" title="Edit Mapping">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteRfidModal{{ $item->id }}" title="Hapus Mapping">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </td>
                </tr>

                {{-- Delete RFID Item Modal --}}
                <div class="modal fade" id="deleteRfidModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('admin.table-expedition.destroy', $item) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus Mapping</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Apakah Anda yakin ingin menghapus mapping RFID <code>{{ $item->rfid_tag }}</code> untuk produk <strong>{{ $item->product?->name }}</strong> dari modul Table Expedition?
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
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-compass fs-1 d-block mb-2 text-secondary"></i>
                        Belum ada pemetaan tag RFID untuk Table Expedition.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Standby Config Modal --}}
<div class="modal fade" id="standbyConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.table-expedition.config') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-display me-2 text-primary"></i>Pengaturan Standby & Panduan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Judul Layar Standby <span class="text-danger">*</span></label>
                        <input type="text" name="standby_title" class="form-control" value="{{ old('standby_title', $standbyTitle) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subjudul / Pesan Standby</label>
                        <input type="text" name="standby_subtitle" class="form-control" value="{{ old('standby_subtitle', $standbySubtitle) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Petunjuk Penggunaan Meja (1 baris per langkah)</label>
                        <textarea name="usage_instructions" class="form-control font-monospace small" rows="5">{{ old('usage_instructions', implode("\n", $instructions)) }}</textarea>
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

@endsection
