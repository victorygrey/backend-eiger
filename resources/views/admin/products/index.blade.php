@extends('layouts.admin')

@section('title', 'List Product')
@section('page-title', 'List Product')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Manajemen Data</a></li>
    <li class="breadcrumb-item active">Products</li>
@endsection

@push('styles')
<style>
    .product-channel-card { border: 0; border-radius: 14px; box-shadow: 0 10px 30px rgba(26, 31, 44, .07); }
    .product-channel-table { min-width: 1120px; }
    .product-channel-table thead th { background: #171b2d; color: #ff6a00; border: 0; font-size: .72rem; letter-spacing: .06em; padding: 1rem; text-transform: uppercase; white-space: nowrap; }
    .product-channel-table tbody td { border-color: #eceff3; padding: .85rem 1rem; vertical-align: middle; }
    .product-channel-table tbody tr { transition: background-color .18s ease; }
    .product-channel-table tbody tr:hover { background: rgba(255, 106, 0, .045); }
    .product-thumb { align-items: center; background: #f6f7f9; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; height: 52px; justify-content: center; overflow: hidden; width: 52px; }
    .product-thumb img { height: 100%; object-fit: contain; padding: 3px; width: 100%; }
    .attribute-chip { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 999px; color: #4b5563; display: inline-flex; font-size: .72rem; font-weight: 600; line-height: 1; padding: .4rem .65rem; }
    .attribute-chip.activity { background: rgba(161, 161, 96, .13); border-color: rgba(161, 161, 96, .3); color: #69692c; }
    .channel-control { align-items: center; display: inline-flex; gap: .55rem; margin: 0; min-width: 128px; }
    .channel-control .form-check-input { cursor: pointer; height: 1.45rem; margin: 0; width: 2.65rem; }
    .channel-control .form-check-input:checked { background-color: #ff6a00; border-color: #ff6a00; }
    .channel-control .form-check-input:disabled { cursor: not-allowed; filter: grayscale(.35); opacity: .45; }
    .channel-control .channel-label { color: #8b93a1; font-size: .75rem; font-weight: 700; white-space: nowrap; }
    .channel-control .form-check-input:checked + .channel-label { color: #198754; }
    .channel-lock-toolbar { align-items: center; background: #f8f9fb; border-bottom: 1px solid #e8ebf0; display: flex; gap: .85rem; justify-content: space-between; padding: .85rem 1rem; }
    .channel-lock-indicator { align-items: center; display: flex; gap: .7rem; min-width: 0; }
    .channel-lock-icon { align-items: center; background: rgba(220, 53, 69, .1); border-radius: 10px; color: #b02a37; display: inline-flex; flex: 0 0 auto; height: 38px; justify-content: center; width: 38px; }
    .channel-lock-title { color: #343a46; font-size: .8rem; font-weight: 800; }
    .channel-lock-help { color: #7b8494; font-size: .72rem; }
    .channel-lock-toolbar.is-unlocked { background: rgba(255, 193, 7, .08); border-bottom-color: rgba(255, 193, 7, .3); }
    .channel-lock-toolbar.is-unlocked .channel-lock-icon { background: rgba(255, 193, 7, .17); color: #8a6400; }
    .channel-lock-toolbar.is-unlocked .channel-lock-title { color: #805b00; }
    @media (max-width: 575.98px) { .channel-lock-toolbar { align-items: stretch; flex-direction: column; } .channel-lock-toolbar .btn { width: 100%; } }
    .filter-label { color: #697181; font-size: .68rem; font-weight: 700; letter-spacing: .05em; margin-bottom: .35rem; text-transform: uppercase; }
    @media (max-width: 1199.98px) { .product-channel-table { min-width: 1040px; } }
</style>
@endpush

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-box-seam-fill text-primary me-2"></i>List Product</h4>
        <p class="text-muted mb-0">Produk diterima otomatis dari PIM. Tentukan ketersediaannya pada AI Fit &amp; Go dan Interactive Tablet secara independen.</p>
    </div>
    <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-3 py-2"><i class="bi bi-cloud-arrow-down-fill me-1"></i>Master data dari PIM</span>
</div>

<div class="card product-channel-card mb-4">
    <div class="card-body p-3 p-lg-4">
        <form method="GET" action="{{ route('admin.products.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-xl">
                <label class="filter-label" for="filter-search">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input id="filter-search" type="search" name="search" class="form-control" placeholder="Cari nama atau SKU..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="filter-label" for="filter-category">Category</label>
                <select id="filter-category" name="category" class="form-select">
                    <option value="">Semua Category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->name }}" @selected(request('category') === $category->name)>{{ $category->name }}</option>
                        @foreach($category->subCategories as $subCategory)
                            <option value="{{ $subCategory->name }}" @selected(request('category') === $subCategory->name)>&nbsp;&nbsp;{{ $subCategory->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="filter-label" for="filter-activity">Activity</label>
                <select id="filter-activity" name="activity" class="form-select">
                    <option value="">Semua Activity</option>
                    @foreach($activityGroups as $group)
                        <option value="{{ $group->name }}" @selected(request('activity') === $group->name)>{{ $group->name }}</option>
                        @foreach($group->activities as $activity)
                            <option value="{{ $activity->name }}" @selected(request('activity') === $activity->name)>&nbsp;&nbsp;{{ $activity->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="filter-label" for="filter-ai">AI Product</label>
                <select id="filter-ai" name="ai_status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="1" @selected(request('ai_status') === '1')>Active</option>
                    <option value="0" @selected(request('ai_status') === '0')>Not Active</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="filter-label" for="filter-tablet">Tablet</label>
                <select id="filter-tablet" name="tablet_status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="1" @selected(request('tablet_status') === '1')>Active</option>
                    <option value="0" @selected(request('tablet_status') === '0')>Not Active</option>
                </select>
            </div>
            <div class="col-12 col-xl-auto d-flex gap-2">
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <button type="submit" class="btn btn-dark flex-grow-1"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card product-channel-card overflow-hidden">
    <div class="card-body p-0">
        <div class="channel-lock-toolbar {{ $channelSettingsUnlocked ? 'is-unlocked' : '' }}" id="channel-lock-toolbar">
            <div class="channel-lock-indicator">
                <span class="channel-lock-icon">
                    <i class="bi {{ $channelSettingsUnlocked ? 'bi-unlock-fill' : 'bi-lock-fill' }}"></i>
                </span>
                <div>
                    <div class="channel-lock-title">Pengaturan kanal {{ $channelSettingsUnlocked ? 'terbuka' : 'terkunci' }}</div>
                    <div class="channel-lock-help">
                        {{ $channelSettingsUnlocked
                            ? 'Switch dapat diubah. Kunci kembali setelah selesai melakukan pengaturan.'
                            : 'Switch AI Product dan Tablet dinonaktifkan untuk mencegah perubahan tidak sengaja.' }}
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.products.channel-lock.update') }}">
                @csrf @method('PATCH')
                <input type="hidden" name="unlocked" value="{{ $channelSettingsUnlocked ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm {{ $channelSettingsUnlocked ? 'btn-warning' : 'btn-outline-dark' }} flex-shrink-0" id="channel-lock-toggle">
                    <i class="bi {{ $channelSettingsUnlocked ? 'bi-lock-fill' : 'bi-unlock-fill' }} me-1"></i>{{ $channelSettingsUnlocked ? 'Kunci Pengaturan' : 'Unlock Pengaturan' }}
                </button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table product-channel-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Activity</th>
                        <th>AI Product</th>
                        <th>Tablet</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $categoryName = $product->atomSubCategory?->name ?? $product->atomCategory?->name ?? $product->category;
                            $activityNames = collect($product->activities)
                                ->map(fn ($activity) => data_get($activity, 'master_activity.name') ?: data_get($activity, 'name'))
                                ->filter()->unique()->values();
                        @endphp
                        <tr>
                            <td><span class="badge-soft badge-gray-soft"><i class="bi bi-upc-scan me-1"></i>{{ $product->sku }}</span></td>
                            <td>
                                <div class="d-flex align-items-center gap-3" style="min-width: 245px;">
                                    <div class="product-thumb flex-shrink-0">
                                        @if($product->image)
                                            <img src="{{ $product->image }}" alt="{{ $product->name }}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                                            <i class="bi bi-image text-muted" style="display:none"></i>
                                        @else
                                            <i class="bi bi-image text-muted"></i>
                                        @endif
                                    </div>
                                    <div class="min-width-0">
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 270px;">{{ $product->name }}</div>
                                        <div class="small text-muted mt-1">{{ $product->variants_count }} varian</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="attribute-chip">{{ $categoryName ?: 'Belum dipetakan' }}</span></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1" style="max-width: 250px;">
                                    @forelse($activityNames->take(2) as $activityName)
                                        <span class="attribute-chip activity">{{ $activityName }}</span>
                                    @empty
                                        <span class="small text-muted">Belum dipetakan</span>
                                    @endforelse
                                    @if($activityNames->count() > 2)
                                        <span class="small text-muted align-self-center">+{{ $activityNames->count() - 2 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.products.channel.update', $product) }}" class="channel-control">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="channel" value="ai_fit_and_go_active">
                                    <input type="hidden" name="active" value="0">
                                    <input class="form-check-input channel-status-toggle" type="checkbox" role="switch" name="active" value="1"
                                        id="ai-product-{{ $product->id }}" @checked($product->ai_fit_and_go_active) @disabled(!$channelSettingsUnlocked) onchange="this.form.submit()">
                                    <label class="channel-label" for="ai-product-{{ $product->id }}">{{ $product->ai_fit_and_go_active ? 'Active' : 'Not Active' }}</label>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.products.channel.update', $product) }}" class="channel-control">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="channel" value="interactive_tablet_active">
                                    <input type="hidden" name="active" value="0">
                                    <input class="form-check-input channel-status-toggle" type="checkbox" role="switch" name="active" value="1"
                                        id="tablet-product-{{ $product->id }}" @checked($product->interactive_tablet_active) @disabled(!$channelSettingsUnlocked) onchange="this.form.submit()">
                                    <label class="channel-label" for="tablet-product-{{ $product->id }}">{{ $product->interactive_tablet_active ? 'Active' : 'Not Active' }}</label>
                                </form>
                            </td>
                            <td class="text-end">
                                <div class="btn-group-actions justify-content-end">
                                    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-outline-primary" title="Lihat detail produk"><i class="bi bi-eye-fill me-1"></i>Detail</a>
                                    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus produk {{ addslashes($product->name) }} ({{ $product->sku }}) dari katalog CMS?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus produk"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">
                            @include('admin.partials.empty-state', [
                                'icon' => 'bi-box',
                                'title' => 'Produk tidak ditemukan',
                                'sub' => 'Ubah kata pencarian atau filter, atau tunggu data produk dikirim dari PIM.',
                            ])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', ['paginator' => $products, 'resource' => 'produk'])
    </div>
</div>
@endsection
