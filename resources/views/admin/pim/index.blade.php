@extends('layouts.admin')

@section('title', 'PIM Integration')
@section('page-title', 'PIM Integration')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Sistem</li>
    <li class="breadcrumb-item active">PIM Integration</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-cloud-arrow-down text-warning me-2"></i>Integrasi Produk PIM</h4>
        <p class="text-muted small mb-0">Kelola sinkronisasi master catalog (nama, gambar, deskripsi) dari PIM Simulator ke CMS.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-box-seam me-1"></i>Lihat Produk
        </a>
        <form action="{{ route('admin.pim.sync') }}" method="POST" class="d-inline" onsubmit="this.querySelector('button').disabled = true;">
            @csrf
            <button type="submit" class="btn btn-eiger" {{ empty($connection['online']) ? 'disabled' : '' }}>
                <i class="bi bi-cloud-arrow-down-fill me-1"></i>Sinkronkan dari PIM
            </button>
        </form>
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

{{-- Stat Cards Row --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Status Server PIM (HTTP API)</div>
                <div class="mt-2">
                    @if(!empty($connection['online']))
                        <span class="badge-soft badge-success-soft fs-6">
                            <i class="bi bi-check-circle-fill"></i> Terhubung (Online)
                        </span>
                    @else
                        <span class="badge-soft badge-danger-soft fs-6">
                            <i class="bi bi-x-circle-fill"></i> Terputus (Offline)
                        </span>
                    @endif
                </div>
                <div class="text-muted small mt-1 font-monospace">{{ $connection['url'] ?? 'http://192.168.18.31:8001' }}</div>
            </div>
            <div class="stat-icon {{ !empty($connection['online']) ? 'bg-success text-success' : 'bg-danger text-danger' }} bg-opacity-10">
                <i class="bi {{ !empty($connection['online']) ? 'bi-hdd-network-fill' : 'bi-hdd-network' }}"></i>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Total Artikel di PIM</div>
                <div class="stat-value mt-2">{{ number_format($connection['article_count'] ?? 0) }}</div>
                <div class="text-muted small">Artikel master katalog tersedia</div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-collection-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Produk Terisi Data PIM</div>
                <div class="stat-value mt-2">{{ number_format($enrichedProducts ?? 0) }}</div>
                <div class="text-muted small">dari {{ number_format($totalCmsProducts ?? 0) }} produk CMS</div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-image-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Mode Folder File-Drop</div>
                <div class="mt-2">
                    <span class="badge-soft {{ $readable ? 'badge-success-soft' : 'badge-warning-soft' }}">
                        <i class="bi {{ $readable ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' }}"></i>
                        {{ $readable ? 'Folder Siap' : 'Belum Tersedia' }}
                    </span>
                </div>
                <div class="text-muted small mt-1">{{ number_format($counts['imported'] ?? 0) }} batch diimpor</div>
            </div>
            <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                <i class="bi bi-folder-fill"></i>
            </div>
        </div>
    </div>
</div>

{{-- HTTP API Sync Card --}}
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Sinkronisasi Langsung (HTTP API)</span>
        <span class="badge bg-primary bg-opacity-10 text-primary">Jalur Utama / Rekomendasi</span>
    </div>
    <div class="card-body">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <p class="mb-2">
                    PIM Simulator menyajikan data katalog lengkap (Nama Resmi, Kategori, Foto Produk, dan Deskripsi). 
                    Tekan tombol <strong>"Sinkronkan Katalog dari PIM"</strong> untuk memperbarui metadata seluruh produk yang ada di CMS secara otomatis.
                </p>
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Status koneksi: <strong>{{ $connection['message'] ?? 'Online' }}</strong> &bull; Endpoint: <code>{{ $connection['url'] ?? '' }}/api/articles/publish-list</code>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <form action="{{ route('admin.pim.sync') }}" method="POST" onsubmit="this.querySelector('button').disabled = true;">
                    @csrf
                    <button type="submit" class="btn btn-eiger px-4 py-2" {{ empty($connection['online']) ? 'disabled' : '' }}>
                        <i class="bi bi-arrow-repeat me-2"></i>Sinkronkan Katalog dari PIM
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- File-Drop Scanner Card --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-folder2-open text-warning me-2"></i>Sumber & Pemindaian (Jalur Alternatif File-Drop NAS)</span>
        <span class="badge-soft {{ $readable ? 'badge-success-soft' : 'badge-warning-soft' }}" role="status">
            <i class="bi {{ $readable ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' }}"></i>
            {{ $readable ? 'Folder siap' : 'Folder belum tersedia' }}
        </span>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-start gap-3 mb-3">
            <div class="p-3 bg-light rounded-3"><i class="bi bi-folder2 text-warning fs-3"></i></div>
            <div class="flex-grow-1" style="min-width: 0;">
                <h6 class="mb-1 fw-bold">Folder konten produk</h6>
                <p class="text-muted small text-break mb-1">{{ $folder }}</p>
                @if(!$readable)
                    <p class="text-warning small mb-1">Folder sumber belum dapat dibaca. Periksa path dan akses folder.</p>
                @endif
                <p class="text-muted small mb-0">
                    <i class="bi bi-clock me-1"></i>
                    @if(config('pim.scan_enabled'))
                        Pemindaian otomatis folder aktif setiap 1 menit saat scheduler berjalan.
                    @else
                        Pemindaian terjadwal nonaktif (Gunakan tombol "Pindai Sekarang" atau "Sinkronkan Katalog dari PIM" di atas).
                    @endif
                </p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.pim.scan') }}" class="d-flex flex-wrap align-items-center gap-3 border-top pt-3" onsubmit="this.querySelector('button').disabled = true;">
            @csrf
            <button type="submit" class="btn btn-outline-primary" {{ !$readable ? 'disabled' : '' }}>
                <i class="bi bi-search me-1"></i>Pindai Paket File-Drop Sekarang
            </button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.pim.index') }}">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh Status
            </a>
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="retry-failed" name="retry_failed" value="1">
                <label class="form-check-label small text-muted" for="retry-failed">Coba ulang batch gagal</label>
            </div>
        </form>
    </div>
</div>

{{-- Riwayat Impor & Batch --}}
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-clock-history text-warning me-2"></i>Riwayat Impor & Sinkronisasi</span>
        <span class="badge-soft badge-gray-soft">{{ number_format($imports->total()) }} batch file-drop</span>
    </div>
    <div class="card-body p-0">
        <div class="px-3 py-3 border-bottom">
            <p class="text-muted small mb-0">Hasil pemrosesan konten PIM di CMS. Paket yang sudah berhasil diimpor akan dilewati pada pemindaian berikutnya.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th class="ps-3">#</th><th>Batch</th><th>Status</th><th class="text-center">Percobaan</th><th>Hasil</th><th class="pe-3">Diperbarui</th></tr>
                </thead>
                <tbody>
                    @forelse($imports as $i => $import)
                        <tr>
                            <td class="ps-3 text-muted">{{ $imports->firstItem() + $i }}</td>
                            <td class="text-break small fw-semibold">{{ $import->batch_id }}</td>
                            <td>
                                <span class="badge-soft {{ $import->status === 'imported' ? 'badge-success-soft' : 'badge-danger-soft' }}">
                                    <i class="bi {{ $import->status === 'imported' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i>{{ $import->status === 'imported' ? 'Berhasil' : 'Gagal' }}
                                </span>
                            </td>
                            <td class="text-center"><span class="badge-soft badge-gray-soft">{{ $import->attempts }}</span></td>
                            <td class="text-muted small text-break">{{ $import->message }}</td>
                            <td class="pe-3 text-muted small text-nowrap"><i class="bi bi-clock me-1"></i>{{ \Illuminate\Support\Carbon::parse($import->updated_at)->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="mb-2"><i class="bi bi-inbox fs-1"></i></div>
                                <div>Belum ada riwayat batch file-drop.</div>
                                <div class="small">Katalog dapat disinkronkan secara langsung menggunakan tombol <strong>"Sinkronkan dari PIM"</strong> di atas.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($imports->hasPages())
            <div class="px-3 py-3 border-top">{{ $imports->links() }}</div>
        @endif
    </div>
</div>
@endsection
