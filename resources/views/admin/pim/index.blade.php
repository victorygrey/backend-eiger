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
        <p class="text-muted small mb-0">Kelola impor konten produk dan pantau hasil sinkronisasi ke katalog CMS.</p>
    </div>
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-box-seam me-1"></i>Lihat Produk
    </a>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Batch Berhasil Diimpor</div>
                <div class="stat-value mt-2">{{ number_format($counts['imported'] ?? 0) }}</div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Batch Gagal</div>
                <div class="stat-value mt-2">{{ number_format($counts['failed'] ?? 0) }}</div>
            </div>
            <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-circle"></i></div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-folder2-open text-warning me-2"></i>Sumber & Pemindaian</span>
        <span class="badge-soft {{ $readable ? 'badge-success-soft' : 'badge-warning-soft' }}" role="status">
            <i class="bi {{ $readable ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' }}"></i>
            {{ $readable ? 'Folder tersedia' : 'Folder belum tersedia' }}
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
                <p class="text-muted small mb-0"><i class="bi bi-clock me-1"></i>{{ config('pim.scan_enabled') ? 'Jadwal setiap menit saat scheduler berjalan.' : 'Pemindaian otomatis nonaktif.' }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.pim.scan') }}" class="d-flex flex-wrap align-items-center gap-3 border-top pt-3" onsubmit="this.querySelector('button').disabled = true;">
            @csrf
            <button type="submit" class="btn btn-eiger" {{ !$readable ? 'disabled' : '' }}><i class="bi bi-arrow-repeat me-1"></i>Pindai Sekarang</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.pim.index') }}"><i class="bi bi-arrow-clockwise me-1"></i>Refresh Status</a>
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="retry-failed" name="retry_failed" value="1">
                <label class="form-check-label small text-muted" for="retry-failed">Coba ulang batch gagal</label>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-clock-history text-warning me-2"></i>Riwayat Impor</span>
        <span class="badge-soft badge-gray-soft">{{ number_format($imports->total()) }} batch</span>
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
                        <tr><td colspan="6">
                            @include('admin.partials.empty-state', [
                                'icon' => 'bi-inbox',
                                'title' => 'Belum ada riwayat impor',
                                'sub' => 'Siapkan konten dari simulator PIM, lalu jalankan pemindaian untuk mengisi katalog.',
                            ])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', ['paginator' => $imports, 'resource' => 'batch'])
    </div>
</div>
@if(config('pim.legacy_http_enabled'))
<div class="mt-3"><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.pim.qa') }}"><i class="bi bi-tools me-1"></i>Alat QA API</a></div>
@endif
@endsection
