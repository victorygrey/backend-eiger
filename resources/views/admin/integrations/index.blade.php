@extends('layouts.admin')

@section('title', 'Integrasi PIM & CARE')
@section('page-title', 'Integrasi PIM & CARE')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Sistem</li>
    <li class="breadcrumb-item active">Integrasi PIM & CARE</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-diagram-3-fill text-warning me-2"></i>Integrasi Sistem PIM & CARE OMNI
        </h4>
        <p class="text-muted small mb-0">
            Pusat kendali terpadu: sinkronisasi master katalog dari <strong>PIM</strong> dan harga ritel serta stok toko dari <strong>CARE Omni</strong>.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-seam me-1"></i>Lihat Produk
        </a>
        <a href="{{ route('admin.sync-logs.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clock-history me-1"></i>Semua Log
        </a>
        <form action="{{ route('admin.integrations.sync-all') }}" method="POST" class="d-inline" onsubmit="this.querySelector('button').disabled = true;">
            @csrf
            <button type="submit" class="btn btn-eiger fw-bold shadow-sm px-3">
                <i class="bi bi-arrow-repeat me-1"></i>Sinkronkan Keduanya Sekarang
            </button>
        </form>
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

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
        <div>{{ session('warning') }}</div>
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

{{-- Stat Cards Row --}}
<div class="row g-3 mb-4">
    {{-- PIM Status --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between h-100">
            <div>
                <div class="stat-label">Server PIM (Katalog)</div>
                <div class="mt-2">
                    @if(!empty($pimConnection['online']))
                        <span class="badge-soft badge-success-soft fs-6">
                            <i class="bi bi-check-circle-fill"></i> Online
                        </span>
                    @else
                        <span class="badge-soft badge-danger-soft fs-6">
                            <i class="bi bi-x-circle-fill"></i> Offline
                        </span>
                    @endif
                </div>
                <div class="text-muted small mt-1 text-truncate font-monospace" style="max-width: 170px;" title="{{ $pimUrl }}">
                    {{ $pimUrl }}
                </div>
            </div>
            <div class="stat-icon {{ !empty($pimConnection['online']) ? 'bg-success text-success' : 'bg-danger text-danger' }} bg-opacity-10">
                <i class="bi {{ !empty($pimConnection['online']) ? 'bi-cloud-check-fill' : 'bi-cloud-slash-fill' }}"></i>
            </div>
        </div>
    </div>

    {{-- CARE Status --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between h-100">
            <div>
                <div class="stat-label">Server CARE (Ritel & Stok)</div>
                <div class="mt-2">
                    @if(!empty($careConnection['online']))
                        <span class="badge-soft badge-success-soft fs-6">
                            <i class="bi bi-check-circle-fill"></i> Online
                        </span>
                    @else
                        <span class="badge-soft badge-danger-soft fs-6">
                            <i class="bi bi-x-circle-fill"></i> Offline
                        </span>
                    @endif
                </div>
                <div class="text-muted small mt-1 text-truncate font-monospace" style="max-width: 170px;" title="{{ $careUrl }}">
                    {{ $storeName }}
                </div>
            </div>
            <div class="stat-icon {{ !empty($careConnection['online']) ? 'bg-success text-success' : 'bg-danger text-danger' }} bg-opacity-10">
                <i class="bi {{ !empty($careConnection['online']) ? 'bi-shop-window' : 'bi-shop' }}"></i>
            </div>
        </div>
    </div>

    {{-- Catalog Count --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between h-100">
            <div>
                <div class="stat-label">Total Katalog CMS</div>
                <div class="stat-value mt-2 fs-5">
                    {{ number_format($totalProducts) }} <span class="fs-6 fw-normal text-muted">Produk</span>
                </div>
                <div class="text-muted small mt-1">
                    <span class="badge bg-secondary bg-opacity-10 text-dark">{{ number_format($totalVariants) }} Varian 12-Digit</span>
                </div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-boxes"></i>
            </div>
        </div>
    </div>

    {{-- Scheduler --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between h-100">
            <div>
                <div class="stat-label">Jadwal Auto-Sync</div>
                <div class="mt-2">
                    <span class="badge-soft badge-success-soft fs-6">
                        <i class="bi bi-clock-history"></i> Tiap 10 Menit
                    </span>
                </div>
                <div class="text-muted small mt-1">
                    Latar Belakang (Scheduler)
                </div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-arrow-repeat"></i>
            </div>
        </div>
    </div>
</div>

{{-- Two Columns: PIM Module & CARE Module --}}
<div class="row g-4 mb-4">
    {{-- PIM Module --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="fw-bold d-flex align-items-center">
                    <span class="p-2 rounded bg-warning bg-opacity-10 text-warning me-2">
                        <i class="bi bi-cloud-arrow-down-fill"></i>
                    </span>
                    <span>1. Master Katalog PIM</span>
                </div>
                <span class="badge bg-light text-dark border">Data Produk Resmi</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    PIM Simulator menyediakan data inti produk: nama resmi, kategori, material, deskripsi, dan foto galeri resolusi tinggi.
                </p>

                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted">Status Koneksi API:</span>
                        @if(!empty($pimConnection['online']))
                            <span class="badge bg-success"><i class="bi bi-check me-1"></i>Terhubung</span>
                        @else
                            <span class="badge bg-danger"><i class="bi bi-x me-1"></i>Terputus</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted">Artikel Siap di PIM:</span>
                        <strong class="text-dark">{{ number_format($pimConnection['article_count'] ?? 0) }} artikel</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Produk Terisi Foto PIM:</span>
                        <strong class="text-dark">{{ number_format($enrichedProducts) }} dari {{ number_format($totalProducts) }}</strong>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-4">
                    <form action="{{ route('admin.integrations.sync-pim') }}" method="POST" class="w-100" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning text-dark w-100 btn-sm fw-semibold" {{ empty($pimConnection['online']) ? 'disabled' : '' }}>
                            <i class="bi bi-cloud-arrow-down me-1"></i>Sinkronkan Katalog PIM Saja
                        </button>
                    </form>
                </div>

                <div class="border-top pt-3 mb-3">
                    <div class="fw-semibold small text-muted mb-2"><i class="bi bi-database-check me-1"></i>Master Data ATOM (17 Sep 2026)</div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark border">{{ $atomMasterCounts['categories'] }} kategori</span>
                        <span class="badge bg-light text-dark border">{{ $atomMasterCounts['sub_categories'] }} subkategori</span>
                        <span class="badge bg-light text-dark border">{{ $atomMasterCounts['activity_groups'] }} grup aktivitas</span>
                        <span class="badge bg-light text-dark border">{{ $atomMasterCounts['activities'] }} aktivitas</span>
                    </div>
                </div>

                {{-- File-Drop Alternative --}}
                <div class="border-top pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small text-muted">
                            <i class="bi bi-folder2-open me-1"></i>Jalur Alternatif: File-Drop NAS
                        </span>
                        <span class="badge-soft {{ $readable ? 'badge-success-soft' : 'badge-warning-soft' }} small">
                            {{ $readable ? 'Folder Siap' : 'Belum Tersedia' }}
                        </span>
                    </div>
                    <div class="text-muted small text-break mb-2 font-monospace" style="font-size: 0.75rem;">
                        {{ $folder }}
                    </div>
                    <form method="POST" action="{{ route('admin.integrations.scan-folder') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <div class="d-flex gap-2 align-items-center">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" {{ !$readable ? 'disabled' : '' }}>
                                <i class="bi bi-search me-1"></i>Pindai Paket File-Drop
                            </button>
                            <div class="form-check mb-0">
                                <input type="checkbox" class="form-check-input" id="retry-failed-sub" name="retry_failed" value="1">
                                <label class="form-check-label small text-muted" for="retry-failed-sub">Retry gagal</label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- CARE Module --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="fw-bold d-flex align-items-center">
                    <span class="p-2 rounded bg-success bg-opacity-10 text-success me-2">
                        <i class="bi bi-shield-check"></i>
                    </span>
                    <span>2. Ritel & Stok CARE OMNI</span>
                </div>
                <span class="badge bg-light text-dark border">Harga & Stok Toko</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    CARE Omni mengelola harga jual ritel dan stok fisik toko aktif untuk setiap SKU varian 12-digit (Fase 1: Setiabudi).
                </p>

                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted">Status Koneksi API:</span>
                        @if(!empty($careConnection['online']))
                            <span class="badge bg-success"><i class="bi bi-check me-1"></i>Terhubung</span>
                        @else
                            <span class="badge bg-danger"><i class="bi bi-x me-1"></i>Terputus</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted">Toko Aktif:</span>
                        <strong class="text-dark">Setiabudi (Kode: {{ $storeCode }})</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted">Produk Berharga:</span>
                        <strong class="text-dark">{{ number_format($pricedProducts) }} produk</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Produk Tersedia Stok (> 0):</span>
                        <strong class="text-dark">{{ number_format($stockedProducts) }} produk</strong>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-4">
                    <form action="{{ route('admin.integrations.sync-care') }}" method="POST" class="w-100" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="btn btn-outline-success w-100 btn-sm fw-semibold" {{ empty($careConnection['online']) ? 'disabled' : '' }}>
                            <i class="bi bi-arrow-repeat me-1"></i>Sinkronkan Harga & Stok CARE Saja
                        </button>
                    </form>
                </div>

                {{-- Toko & Endpoint Detail --}}
                <div class="border-top pt-3">
                    <div class="small text-muted mb-1">
                        <i class="bi bi-info-circle me-1"></i>Informasi Endpoint CARE Omni:
                    </div>
                    <div class="text-muted small text-break font-monospace" style="font-size: 0.75rem;">
                        {{ $careMasterUrl }}/api/server/pricing_details<br>
                        {{ $careWmsUrl }}/api/server/inventories/bybin
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Unified Sync History & Logs Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold">
            <i class="bi bi-clock-history text-warning me-2"></i>Riwayat Sinkronisasi Terbaru (PIM & CARE)
        </span>
        <a href="{{ route('admin.sync-logs.index') }}" class="btn btn-outline-secondary btn-sm">
            Lihat Semua Log
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 5%;">#</th>
                        <th style="width: 15%;">Sumber Integrasi</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 50%;">Pesan Aktivitas</th>
                        <th class="pe-3 text-end" style="width: 18%;">Waktu Eksekusi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($latestLogs as $i => $log)
                        <tr>
                            <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                            <td>
                                @if(str_starts_with($log->source, 'pim'))
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning">
                                        <i class="bi bi-cloud-arrow-down me-1"></i>{{ strtoupper($log->source) }}
                                    </span>
                                @elseif(str_starts_with($log->source, 'care'))
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                        <i class="bi bi-shield-check me-1"></i>{{ strtoupper($log->source) }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary">{{ strtoupper($log->source) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($log->status === 'success')
                                    <span class="badge-soft badge-success-soft">
                                        <i class="bi bi-check-circle-fill"></i> Berhasil
                                    </span>
                                @else
                                    <span class="badge-soft badge-danger-soft">
                                        <i class="bi bi-x-circle-fill"></i> Gagal
                                    </span>
                                @endif
                            </td>
                            <td class="small text-break">{{ $log->message }}</td>
                            <td class="pe-3 text-end text-muted small text-nowrap">
                                <i class="bi bi-clock me-1"></i>{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d M Y H:i:s') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted small">
                                Belum ada riwayat sinkronisasi. Tekan tombol <strong>"Sinkronkan Keduanya Sekarang"</strong> untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
