@extends('layouts.admin')

@section('title', 'Manajemen Products')
@section('page-title', 'Daftar Produk')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Manajemen Data</a></li>
    <li class="breadcrumb-item active">Products</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-box-seam-fill text-primary me-2"></i>Katalog Produk EIGER</h4>
        <p class="text-muted small mb-0">Kelola semua produk digital store dan lokasi zona.</p>
    </div>
    <a href="{{ route('admin.products.create') }}" class="btn btn-eiger">
        <i class="bi bi-plus-lg me-1"></i>Tambah Produk
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.products.index') }}" class="row g-3">
            <div class="col-12 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan nama atau SKU..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-md-4">
                <select name="zone_id" class="form-select">
                    <option value="">-- Semua Zone --</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                @if(request('search') || request('zone_id'))
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
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
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th class="text-center" style="width: 70px;">Photo</th>
                        <th>Zone</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th class="pe-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $i => $product)
                        <tr>
                            <td class="ps-3 text-muted">{{ $products->firstItem() + $i }}</td>
                            <td>
                                <span class="badge-soft badge-gray-soft"><i class="bi bi-upc-scan me-1"></i>{{ $product->sku }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $product->name }}</div>
                                @if($product->variants->isNotEmpty())
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small text-primary d-inline-flex align-items-center gap-1"
                                            data-bs-toggle="collapse" data-bs-target="#variants-{{ $product->id }}" aria-expanded="false">
                                            <i class="bi bi-diagram-3-fill"></i>
                                            <span>Lihat {{ $product->variants->count() }} Varian SKU</span>
                                            <i class="bi bi-chevron-down small"></i>
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($product->image)
                                    <img src="{{ $product->image }}" alt="{{ $product->name }}"
                                        class="rounded border bg-white shadow-sm"
                                        style="width: 48px; height: 48px; object-fit: contain; padding: 2px;"
                                        loading="lazy"
                                        onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'rounded border bg-light d-flex align-items-center justify-content-center text-muted mx-auto\' style=\'width:48px;height:48px;\'><i class=\'bi bi-image text-muted\'></i></div>';">
                                @else
                                    <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted mx-auto" style="width: 48px; height: 48px;">
                                        <i class="bi bi-image text-muted"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($product->zone)
                                    <span class="badge-soft badge-info-soft">
                                        <i class="bi bi-geo-alt-fill"></i>{{ $product->zone->name }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="fw-semibold">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                            <td>
                                @if($product->stock > 10)
                                    <span class="badge-soft badge-success-soft">{{ number_format($product->stock) }}</span>
                                @elseif($product->stock > 0)
                                    <span class="badge-soft badge-warning-soft">{{ number_format($product->stock) }}</span>
                                @else
                                    <span class="badge-soft badge-danger-soft">Habis</span>
                                @endif
                            </td>
                            <td>
                                @if($product->is_discontinued)
                                    <span class="badge-soft badge-danger-soft"><i class="bi bi-slash-circle"></i>Discontinued</span>
                                @elseif($product->is_featured)
                                    <span class="badge-soft badge-warning-soft"><i class="bi bi-star-fill"></i>Featured</span>
                                @else
                                    <span class="badge-soft badge-success-soft"><i class="bi bi-check-circle-fill"></i>Active</span>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group-actions">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Yakin hapus produk {{ $product->sku }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @if($product->variants->isNotEmpty())
                            <tr class="collapse bg-light" id="variants-{{ $product->id }}">
                                <td colspan="9" class="p-3">
                                    <div class="card border border-primary border-opacity-25 shadow-sm rounded-3">
                                        <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="small fw-bold text-dark">
                                                <i class="bi bi-boxes text-primary me-1"></i> Rincian Varian ({{ $product->variants->count() }} Item) &bull; Produk Induk: <code class="text-dark">{{ $product->sku }}</code>
                                            </span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary small">Toko: Setiabudi (2022)</span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover mb-0 align-middle">
                                                <thead class="table-light small text-muted">
                                                    <tr>
                                                        <th class="ps-3">SKU Varian (12 Digit)</th>
                                                        <th class="text-center" style="width: 60px;">Foto</th>
                                                        <th>Nama Varian</th>
                                                        <th>Warna</th>
                                                        <th>Ukuran</th>
                                                        <th class="text-end">Harga Varian</th>
                                                        <th class="text-center">Stok Toko</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($product->variants as $v)
                                                        <tr>
                                                            <td class="ps-3"><code class="fw-bold text-dark">{{ $v->sku }}</code></td>
                                                            <td class="text-center">
                                                                @php $varImg = $v->image ?: $product->image; @endphp
                                                                @if($varImg)
                                                                    <img src="{{ $varImg }}" alt="{{ $v->name }}"
                                                                        class="rounded border bg-white shadow-sm"
                                                                        style="width: 36px; height: 36px; object-fit: contain; padding: 1px;"
                                                                        loading="lazy"
                                                                        onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'rounded border bg-light d-flex align-items-center justify-content-center text-muted mx-auto\' style=\'width:36px;height:36px;\'><i class=\'bi bi-image small text-muted\'></i></div>';">
                                                                @else
                                                                    <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted mx-auto" style="width: 36px; height: 36px;">
                                                                        <i class="bi bi-image small text-muted"></i>
                                                                    </div>
                                                                @endif
                                                            </td>
                                                            <td class="small">{{ $v->name }}</td>
                                                            <td><span class="badge bg-secondary">{{ $v->color ?? '—' }}</span></td>
                                                            <td><span class="badge bg-dark">{{ $v->size ?? '—' }}</span></td>
                                                            <td class="text-end fw-bold text-success small">Rp {{ number_format($v->price, 0, ',', '.') }}</td>
                                                            <td class="text-center">
                                                                <span class="badge {{ $v->stock > 0 ? 'bg-success' : 'bg-danger' }} px-2 py-1">
                                                                    {{ $v->stock }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="9">
                            @include('admin.partials.empty-state', [
                                'icon'        => 'bi-box',
                                'title'       => 'Belum ada produk',
                                'sub'         => 'Tambahkan produk pertama untuk mengisi katalog.',
                                'actionUrl'   => route('admin.products.create'),
                                'actionLabel' => 'Tambah Produk',
                            ])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', [
            'paginator' => $products,
            'resource'  => 'produk',
        ])
    </div>
</div>
@endsection
