@extends('layouts.admin')

@section('title', 'CARE Integration')
@section('page-title', 'CARE Integration')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Sistem</li>
    <li class="breadcrumb-item active">CARE Integration</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-shield-check text-warning me-2"></i>Integrasi Harga & Stok CARE OMNI</h4>
        <p class="text-muted small mb-0">Kelola sinkronisasi harga ritel dan stok produk dari CARE Omni (Fase 1: Toko Setiabudi).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-box-seam me-1"></i>Lihat Katalog Produk
        </a>
        <a href="{{ route('admin.sync-logs.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i>Lihat Log
        </a>
    </div>
</div>

{{-- Flash Message --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5 me-2"></i>
        <div>{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
        <div>{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Status Server CARE</div>
                <div class="mt-2">
                    @if($connection['online'])
                        <span class="badge-soft badge-success-soft fs-6">
                            <i class="bi bi-check-circle-fill"></i> Terhubung
                        </span>
                    @else
                        <span class="badge-soft badge-danger-soft fs-6">
                            <i class="bi bi-x-circle-fill"></i> Terputus
                        </span>
                    @endif
                </div>
            </div>
            <div class="stat-icon {{ $connection['online'] ? 'bg-success text-success' : 'bg-danger text-danger' }} bg-opacity-10">
                <i class="bi {{ $connection['online'] ? 'bi-hdd-network-fill' : 'bi-hdd-network' }}"></i>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Toko Aktif (Fase 1)</div>
                <div class="stat-value mt-2 fs-5 text-truncate" style="max-width: 170px;" title="{{ $storeName }}">
                    Setiabudi
                </div>
                <div class="text-muted small">Kode Lokasi: <strong>{{ $storeCode }}</strong></div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-shop"></i>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Produk Berharga</div>
                <div class="stat-value mt-2">{{ number_format($pricedProducts) }}</div>
                <div class="text-muted small">dari {{ number_format($totalProducts) }} produk</div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-tag-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Produk Tersedia Stok</div>
                <div class="stat-value mt-2">{{ number_format($stockedProducts) }}</div>
                <div class="text-muted small">Stok fisik toko &gt; 0</div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-boxes"></i>
            </div>
        </div>
    </div>
</div>

{{-- Control & Trigger Card --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-gear-wide-connected text-warning me-2"></i>Konfigurasi & Penarikan Data</span>
        <span class="badge-soft {{ $connection['online'] ? 'badge-success-soft' : 'badge-warning-soft' }}">
            <i class="bi {{ $connection['online'] ? 'bi-wifi' : 'bi-wifi-off' }}"></i>
            {{ $connection['online'] ? 'Simulator Siap' : 'Periksa URL Simulator' }}
        </span>
    </div>
    <div class="card-body">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-start gap-3">
                    <div class="p-3 bg-light rounded-3 text-warning fs-3">
                        <i class="bi bi-cloud-arrow-down-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">Target Endpoint CARE Simulator</h6>
                        <p class="text-muted small mb-2">
                            Base URL: <code class="text-dark bg-light px-2 py-1 rounded">{{ $careUrl }}</code>
                            &bull; Toko: <strong>{{ $storeName }} ({{ $storeCode }})</strong>
                        </p>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle me-1 text-primary"></i>
                            Sesuai MoM Poin 4, penarikan data harga dan stok otomatis dijalankan via Scheduler (pagi dan pergantian shift) dengan parameter <code>filter[loccode]={{ $storeCode }}</code>. Anda juga dapat memicu penarikan instan melalui tombol di sebelah kanan.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <form method="POST" action="{{ route('admin.care.sync') }}" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('.spinner-border').classList.remove('d-none');">
                    @csrf
                    <button type="submit" class="btn btn-eiger btn-lg w-100 mb-2">
                        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                        <i class="bi bi-arrow-repeat me-1"></i> Tarik Data CARE Sekarang
                    </button>
                    <div class="text-muted small text-center">
                        Terakhir sinkronisasi: 
                        <strong>{{ $lastSync ? $lastSync->synced_at->diffForHumans() : 'Belum pernah' }}</strong>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Recent Products & Logs Grid --}}
<div class="row g-4">
    {{-- Sample Products Synced --}}
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box-seam text-warning me-2"></i>Sampel Produk Hasil Sinkronisasi CARE</span>
                <span class="badge-soft badge-gray-soft">{{ number_format($pricedProducts) }} produk aktif</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">SKU</th>
                                <th>Nama Produk</th>
                                <th class="text-end">Harga (IDR)</th>
                                <th class="text-center">Stok</th>
                                <th class="pe-3 text-end">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentProducts as $p)
                                <tr>
                                    <td class="ps-3">
                                        <code class="text-dark fw-bold">{{ $p->sku }}</code>
                                        @if(strlen($p->sku) === 9)
                                            <span class="badge-soft badge-info-soft ms-1">Generik</span>
                                        @elseif(strlen($p->sku) === 12)
                                            <span class="badge-soft badge-warning-soft ms-1">Varian</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-truncate" style="max-width: 220px;" title="{{ $p->name }}">{{ $p->name }}</div>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        Rp {{ number_format($p->price, 0, ',', '.') }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $p->stock > 0 ? 'bg-success' : 'bg-secondary' }} px-2 py-1">
                                            {{ $p->stock }}
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end text-muted small">
                                        {{ $p->updated_at ? $p->updated_at->format('H:i:s') : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        Belum ada produk yang disinkronkan dari CARE.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent CARE Logs --}}
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history text-warning me-2"></i>Log Sinkronisasi Terakhir</span>
                <a href="{{ route('admin.sync-logs.index', ['source' => 'care-web']) }}" class="small text-decoration-none">Semua Log &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Sumber</th>
                                <th>Status</th>
                                <th class="pe-3 text-end">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($latestLogs as $log)
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge-soft badge-info-soft">
                                            <i class="bi bi-terminal me-1"></i>{{ $log->source }}
                                        </span>
                                        <div class="text-muted small mt-1 text-truncate" style="max-width: 200px;" title="{{ $log->message }}">
                                            {{ $log->message }}
                                        </div>
                                    </td>
                                    <td>
                                        @if(strtolower($log->status) === 'success')
                                            <span class="badge-soft badge-success-soft">
                                                <i class="bi bi-check-circle-fill"></i> Sukses
                                            </span>
                                        @else
                                            <span class="badge-soft badge-danger-soft">
                                                <i class="bi bi-x-circle-fill"></i> Gagal
                                            </span>
                                        @endif
                                    </td>
                                    <td class="pe-3 text-end text-muted small text-nowrap">
                                        {{ $log->synced_at ? $log->synced_at->format('d M H:i') : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        Belum ada riwayat log sinkronisasi CARE.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
