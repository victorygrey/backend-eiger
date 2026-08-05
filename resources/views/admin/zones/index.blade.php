@extends('layouts.admin')

@section('title', 'Manajemen Zones')
@section('page-title', 'Daftar Zone')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Manajemen Data</a></li>
    <li class="breadcrumb-item active">Zones</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-map-fill text-success me-2"></i>Manajemen Zone</h4>
        <p class="text-muted small mb-0">Kelola zona lokasi untuk produk di Digital Store.</p>
    </div>
    <a href="{{ route('admin.zones.create') }}" class="btn btn-eiger">
        <i class="bi bi-plus-lg me-1"></i>Tambah Zone
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.zones.index') }}" class="row g-3">
            <div class="col-12 col-md-8">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama zone..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button class="btn btn-dark w-100"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                @if(request('search'))
                    <a href="{{ route('admin.zones.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Nama Zone</th>
                        <th>Deskripsi</th>
                        <th class="text-center">Jumlah Produk</th>
                        <th>Dibuat</th>
                        <th class="pe-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zones as $i => $zone)
                        <tr>
                            <td class="ps-3 text-muted">{{ $zones->firstItem() + $i }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="p-2 rounded text-white" style="background:linear-gradient(135deg,#10b981,#047857)">
                                        <i class="bi bi-geo-alt-fill"></i>
                                    </div>
                                    <span class="fw-semibold">{{ $zone->name }}</span>
                                </div>
                            </td>
                            <td class="text-muted">{{ \Str::limit($zone->description, 60) ?: '—' }}</td>
                            <td class="text-center">
                                <span class="badge-soft badge-info-soft">
                                    <i class="bi bi-box-seam"></i>{{ $zone->products_count }}
                                </span>
                            </td>
                            <td class="text-muted small">
                                <i class="bi bi-calendar3 me-1"></i>{{ $zone->created_at->format('d M Y') }}
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group-actions">
                                    <a href="{{ route('admin.zones.edit', $zone) }}" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form action="{{ route('admin.zones.destroy', $zone) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Yakin hapus zone {{ $zone->name }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">
                            @include('admin.partials.empty-state', [
                                'icon'        => 'bi-map',
                                'title'       => 'Belum ada zone',
                                'sub'         => 'Tambahkan zone pertama untuk mengelompokkan produk.',
                                'actionUrl'   => route('admin.zones.create'),
                                'actionLabel' => 'Tambah Zone',
                            ])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', [
            'paginator' => $zones,
            'resource'  => 'zone',
        ])
    </div>
</div>
@endsection
