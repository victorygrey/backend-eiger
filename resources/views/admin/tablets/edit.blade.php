@extends('layouts.admin')

@section('title', 'Edit '.$tablet->name)
@section('page-title', 'Konfigurasi Interactive Table')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.tablets.index') }}">Interactive Table & Display</a></li>
    <li class="breadcrumb-item active">{{ $tablet->name }}</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="mb-0 fw-bold">{{ $tablet->name }}</h4>
            <span class="badge bg-secondary font-monospace">v{{ $tablet->config_version }}</span>
            @if($tablet->is_active)
                <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Aktif</span>
            @else
                <span class="badge bg-secondary">Nonaktif</span>
            @endif
        </div>
        <p class="text-muted small mb-0">
            Perbarui produk utama, urutan rekomendasi, atau buat kode aktivasi baru untuk pairing tablet.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tabletPreviewModal">
            <i class="bi bi-display me-1"></i>Lihat Preview Layar Tablet
        </button>
        <a href="{{ route('admin.tablets.index') }}" class="btn btn-outline-secondary">
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

<form method="POST" action="{{ route('admin.tablets.update', $tablet) }}">
    @csrf
    @method('PUT')
    @include('admin.tablets._form')
</form>

{{-- Version Rollback Card --}}
<div class="card border-0 shadow-sm rounded-3 mt-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Versi Konfigurasi (Rollback)</h6>
            <small class="text-muted">Kembalikan konfigurasi tablet ke versi sebelumnya sewaktu-waktu.</small>
        </div>
        <span class="badge bg-secondary">{{ $tablet->versions->count() }} Snapshot</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 140px;">Versi</th>
                        <th>Featured Product (Utama)</th>
                        <th>Jumlah Rekomendasi</th>
                        <th>Waktu Publikasi</th>
                        <th class="text-end pe-3" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tablet->versions as $version)
                        <tr>
                            <td class="ps-3 fw-bold">
                                <span class="font-monospace">v{{ $version->version_number }}</span>
                                @if($version->version_number === $tablet->config_version)
                                    <span class="badge badge-success-soft ms-2"><i class="bi bi-check2"></i> Aktif</span>
                                @endif
                            </td>
                            <td>
                                @if($version->featuredProduct)
                                    <div class="fw-semibold text-dark">{{ $version->featuredProduct->name }}</div>
                                    <div class="small text-muted font-monospace">{{ $version->featuredProduct->sku }}</div>
                                @else
                                    <span class="text-muted fst-italic">Produk tidak lagi tersedia</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ count($version->recommendation_product_ids ?? []) }} produk rekomendasi
                                </span>
                            </td>
                            <td class="small text-muted">
                                {{ $version->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                            </td>
                            <td class="text-end pe-3">
                                @if($version->version_number !== $tablet->config_version)
                                    <form method="POST" action="{{ route('admin.tablets.rollback', [$tablet, $version]) }}"
                                          onsubmit="return confirm('Pulihkan konfigurasi tablet ke versi v{{ $version->version_number }}?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan
                                        </button>
                                    </form>
                                @else
                                    <span class="badge bg-light text-success border border-success-subtle">Versi Berjalan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada riwayat snapshot versi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL: Tablet Display Mock Preview --}}
<div class="modal fade" id="tabletPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: #0f172a; color: #fff;">
            <div class="modal-header border-secondary">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary">Simulation</span>
                    <h5 class="modal-title fw-bold text-white mb-0">Simulasi Tampilan Layar: {{ $tablet->name }}</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Tablet Mock Frame --}}
                <div class="border border-secondary rounded-4 p-4 shadow" style="background: #020617;">
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold fs-5 text-warning">EIGER</span>
                            <span class="text-secondary">|</span>
                            <span class="small text-muted font-monospace">Interactive Table Product Knowledge</span>
                        </div>
                        <span class="badge bg-dark border border-secondary text-secondary">
                            {{ $tablet->location ?: 'Store Display' }}
                        </span>
                    </div>

                    {{-- Main Product Highlight --}}
                    @if($tablet->featuredProduct)
                        <div class="row g-4 align-items-center mb-5">
                            <div class="col-md-5 text-center">
                                <img src="{{ $tablet->featuredProduct->image ?: 'https://placehold.co/400x400?text=EIGER' }}"
                                     alt="{{ $tablet->featuredProduct->name }}"
                                     class="img-fluid rounded-3 shadow-lg border border-secondary" style="max-height: 280px;">
                            </div>
                            <div class="col-md-7">
                                <span class="badge bg-warning text-dark fw-bold mb-2">PRODUK STANDBY</span>
                                <h3 class="fw-bold text-white mb-1">{{ $tablet->featuredProduct->name }}</h3>
                                <div class="text-muted small font-monospace mb-3">SKU: {{ $tablet->featuredProduct->sku }}</div>
                                <h4 class="text-warning fw-bold mb-3">Rp {{ number_format($tablet->featuredProduct->price ?? 0, 0, ',', '.') }}</h4>
                                <p class="text-secondary small mb-3">
                                    {{ Str::limit($tablet->featuredProduct->description ?: 'Produk resmi EIGER untuk aktivitas penjelajahan luar ruang dan kegiatan harian.', 180) }}
                                </p>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-dark border border-secondary text-light">Material: {{ $tablet->featuredProduct->material ?: 'Polyester' }}</span>
                                    <span class="badge bg-dark border border-secondary text-light">Zone: {{ $tablet->featuredProduct->zone?->name ?? 'Outdoor Zone' }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-5 text-secondary">
                            <i class="bi bi-exclamation-circle fs-1 d-block mb-2"></i>
                            Belum ada Featured Product yang dipilih.
                        </div>
                    @endif

                    {{-- Recommendations Bar --}}
                    <div class="border-top border-secondary pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-light fw-bold mb-0">Rekomendasi Produk Serupa ({{ $tablet->recommendations->count() }})</h6>
                            <small class="text-muted">Tap item untuk melihat komparasi</small>
                        </div>
                        <div class="d-flex gap-3 overflow-auto pb-2">
                            @forelse($tablet->recommendations as $rec)
                                <div class="p-2 rounded bg-dark border border-secondary flex-shrink-0 text-center" style="width: 140px;">
                                    <img src="{{ $rec->image ?: 'https://placehold.co/100x100?text=EIGER' }}"
                                         alt="{{ $rec->name }}" class="rounded mb-2 object-fit-cover w-100" style="height: 90px;">
                                    <div class="fw-semibold text-truncate small text-white" title="{{ $rec->name }}">{{ $rec->name }}</div>
                                    <div class="text-warning small fw-bold">Rp {{ number_format($rec->price ?? 0, 0, ',', '.') }}</div>
                                </div>
                            @empty
                                <div class="text-muted small py-2">Tidak ada rekomendasi produk.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <a href="{{ url('/api/tablets/'.$tablet->slug.'/display') }}" target="_blank" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Buka API JSON Endpoint
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection
