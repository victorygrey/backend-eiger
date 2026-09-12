@extends('layouts.admin')

@section('title', 'Interactive Table & Display')
@section('page-title', 'Interactive Table & Display')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Konfigurasi</li>
    <li class="breadcrumb-item active">Interactive Table & Display</li>
@endsection

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-tablet-landscape-fill text-primary me-2"></i>Interactive Table & Display
        </h4>
        <p class="text-muted small mb-0">
            Kelola konfigurasi perangkat layar sentuh meja ekspedisi (Table Product Knowledge) dan tablet display katalog produk EIGER.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.tablets.create') }}" class="btn btn-eiger fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Perangkat Table / Display
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

{{-- Quick Stat Summary --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $tablets->total() }}</div>
                    <div class="stat-label">Total Perangkat</div>
                </div>
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-tablet-landscape"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                @php
                    $onlineCount = $tablets->filter(fn($t) => $t->is_active && ($t->last_seen_at?->gt(now()->subMinutes(2)) ?? false))->count();
                @endphp
                <div>
                    <div class="stat-value text-success">{{ $onlineCount }}</div>
                    <div class="stat-label">Perangkat Online</div>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-wifi"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-warning">v{{ $tablets->max('config_version') ?? 1 }}</div>
                    <div class="stat-label">Versi Terkini</div>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-layers-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-secondary">{{ $tablets->where('is_active', true)->count() }}</div>
                    <div class="stat-label">Perangkat Aktif</div>
                </div>
                <div class="stat-icon bg-secondary-subtle text-secondary">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Main Tablets Table --}}
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <h6 class="mb-0 fw-bold"><i class="bi bi-hdd-network me-2 text-primary"></i>Daftar Perangkat Interactive Table</h6>
            <span class="badge bg-secondary">{{ $tablets->total() }} Terdaftar</span>
        </div>
        <div class="input-group input-group-sm" style="max-width: 280px;">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="filter-table-input" class="form-control" placeholder="Cari nama atau lokasi tablet...">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablet-list-table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 220px;">Perangkat & Lokasi</th>
                        <th>Featured Product (Standby)</th>
                        <th>Rekomendasi Produk</th>
                        <th style="width: 90px;">Versi</th>
                        <th style="width: 140px;">Status Koneksi</th>
                        <th class="pe-3 text-end" style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tablets as $tablet)
                        @php($online = $tablet->is_active && ($tablet->last_seen_at?->gt(now()->subMinutes(2)) ?? false))
                        <tr class="tablet-row" data-name="{{ strtolower($tablet->name) }}" data-location="{{ strtolower($tablet->location ?? '') }}">
                            <td class="ps-3">
                                <div class="fw-bold text-dark">{{ $tablet->name }}</div>
                                <div class="small text-muted font-monospace d-flex align-items-center gap-1">
                                    <span>/tablet/{{ $tablet->slug }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-muted" onclick="copySlug('{{ $tablet->slug }}')" title="Salin Slug">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="small text-secondary mt-1">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $tablet->location ?: 'Lokasi belum diatur' }}
                                </div>
                            </td>
                            <td>
                                @if($tablet->featuredProduct)
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $tablet->featuredProduct->image ?: 'https://placehold.co/50x50?text=EIGER' }}"
                                             alt="{{ $tablet->featuredProduct->name }}"
                                             class="rounded border object-fit-cover shadow-sm flex-shrink-0"
                                             style="width: 44px; height: 44px;">
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold small text-truncate text-dark" style="max-width: 220px;" title="{{ $tablet->featuredProduct->name }}">
                                                {{ $tablet->featuredProduct->name }}
                                            </div>
                                            <div class="small text-muted font-monospace">{{ $tablet->featuredProduct->sku }}</div>
                                            <div class="small text-primary fw-semibold">Rp {{ number_format($tablet->featuredProduct->price ?? 0, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="badge badge-warning-soft"><i class="bi bi-exclamation-triangle me-1"></i>Belum diatur</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    {{-- Avatars of first 3 recommendations --}}
                                    <div class="d-flex align-items-center">
                                        @foreach($tablet->recommendations->take(3) as $rec)
                                            <img src="{{ $rec->image ?: 'https://placehold.co/40x40?text=E' }}"
                                                 alt="{{ $rec->name }}"
                                                 class="rounded-circle border border-white shadow-sm object-fit-cover"
                                                 title="{{ $rec->name }} ({{ $rec->sku }})"
                                                 style="width: 32px; height: 32px; margin-left: -8px; first-child: margin-left: 0;">
                                        @endforeach
                                    </div>
                                    <span class="badge bg-light text-dark border">
                                        {{ $tablet->recommendations_count }} produk
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary font-monospace">v{{ $tablet->config_version }}</span>
                            </td>
                            <td>
                                @if(!$tablet->is_active)
                                    <span class="badge badge-gray-soft"><i class="bi bi-pause-circle"></i> Nonaktif</span>
                                @elseif($online)
                                    <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill"></i> Online</span>
                                @else
                                    <span class="badge badge-warning-soft"><i class="bi bi-slash-circle"></i> Offline</span>
                                @endif
                                <div class="small text-muted mt-1">
                                    {{ $tablet->last_seen_at ? $tablet->last_seen_at->diffForHumans() : 'Belum pernah' }}
                                </div>
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ url('/api/tablets/'.$tablet->slug.'/display') }}" target="_blank" class="btn btn-outline-secondary" title="Lihat Output JSON API">
                                        <i class="bi bi-code-slash"></i>
                                    </a>
                                    <a href="{{ route('admin.tablets.edit', $tablet) }}" class="btn btn-outline-primary" title="Edit Konfigurasi">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTabletModal{{ $tablet->id }}" title="Hapus">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- Delete Confirmation Modal --}}
                        <div class="modal fade" id="deleteTabletModal{{ $tablet->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('admin.tablets.destroy', $tablet) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Hapus Perangkat Table</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            Apakah Anda yakin ingin menghapus konfigurasi <strong>{{ $tablet->name }}</strong> (<code>/tablet/{{ $tablet->slug }}</code>)?
                                            Tindakan ini akan memutus akses tablet tersebut ke katalog.
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-danger fw-bold">Hapus Tablet</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-tablet-landscape fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada perangkat Interactive Table / Display yang terdaftar.
                                <div class="mt-3">
                                    <a href="{{ route('admin.tablets.create') }}" class="btn btn-sm btn-eiger">
                                        <i class="bi bi-plus-lg me-1"></i>Tambah Perangkat Pertama
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', ['paginator' => $tablets, 'resource' => 'tablet'])
    </div>
</div>

@push('scripts')
<script>
    // Live filter table rows
    document.getElementById('filter-table-input')?.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#tablet-list-table .tablet-row').forEach(row => {
            const name = row.dataset.name || '';
            const loc = row.dataset.location || '';
            row.style.display = (name.includes(q) || loc.includes(q)) ? '' : 'none';
        });
    });

    // Copy slug
    function copySlug(slug) {
        navigator.clipboard.writeText(slug).then(() => {
            alert(`Slug '${slug}' berhasil disalin ke clipboard!`);
        });
    }
</script>
@endpush
@endsection
