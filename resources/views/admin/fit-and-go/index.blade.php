@extends('layouts.admin')

@section('title', 'AI Fit & Go Configuration')
@section('page-title', 'AI Fit & Go')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Konfigurasi</li>
    <li class="breadcrumb-item active">AI Fit &amp; Go</li>
@endsection

@push('styles')
<style>
    .fit-config-shell { --fit-orange: #ff6500; --fit-navy: #171b2d; }
    .fit-config-card { border: 0; border-radius: 16px; box-shadow: 0 10px 32px rgba(24, 30, 48, .07); }
    .fit-summary { background: linear-gradient(135deg, #171b2d 0%, #252b44 100%); border-radius: 16px; color: #fff; overflow: hidden; padding: 1.35rem; position: relative; }
    .fit-summary::after { background: var(--fit-orange); border-radius: 50%; content: ''; height: 150px; opacity: .15; position: absolute; right: -55px; top: -70px; width: 150px; }
    .fit-summary .summary-icon { align-items: center; background: rgba(255, 101, 0, .18); border: 1px solid rgba(255, 101, 0, .35); border-radius: 12px; color: #ff8b3d; display: flex; height: 44px; justify-content: center; width: 44px; }
    .fit-summary-label { color: #aeb5c8; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .fit-summary-value { font-size: 1.45rem; font-weight: 800; line-height: 1.15; }
    .config-tabs .nav-link { border: 0; border-bottom: 3px solid transparent; color: #7b8494; font-weight: 700; padding: .85rem 1rem; }
    .config-tabs .nav-link.active { background: transparent; border-bottom-color: var(--fit-orange); color: var(--fit-orange); }
    .wahana-pill { align-items: center; background: #f7f8fa; border: 1px solid #e3e6eb; border-radius: 12px; display: flex; gap: .65rem; padding: .7rem .85rem; }
    .wahana-pill .icon { align-items: center; background: #fff; border-radius: 9px; color: var(--fit-orange); display: flex; height: 34px; justify-content: center; width: 34px; }
    .wahana-pill.active { background: rgba(255, 101, 0, .08); border-color: rgba(255, 101, 0, .45); box-shadow: inset 3px 0 0 var(--fit-orange); }
    .wahana-pill:hover { border-color: rgba(255, 101, 0, .35); }
    .mode-option { cursor: pointer; display: block; height: 100%; position: relative; }
    .mode-option input { opacity: 0; position: absolute; }
    .mode-option-body { background: #fff; border: 1px solid #e3e6eb; border-radius: 13px; height: 100%; padding: 1rem; transition: .18s ease; }
    .mode-option input:checked + .mode-option-body { background: rgba(255, 101, 0, .055); border-color: var(--fit-orange); box-shadow: 0 0 0 2px rgba(255, 101, 0, .12); }
    .mode-option-check { color: #b5bbc5; }
    .mode-option input:checked + .mode-option-body .mode-option-check { color: var(--fit-orange); }
    .group-card { border: 1px solid #e8ebef; border-radius: 15px; overflow: hidden; }
    .group-header { align-items: center; background: #fafbfc; border-bottom: 1px solid #e8ebef; display: flex; gap: .8rem; justify-content: space-between; padding: .95rem 1rem; }
    .group-title { align-items: center; display: flex; gap: .7rem; }
    .group-title-icon { align-items: center; background: rgba(255, 101, 0, .11); border-radius: 10px; color: var(--fit-orange); display: flex; height: 38px; justify-content: center; width: 38px; }
    .fit-product { border: 1px solid #e8ebef; border-radius: 13px; height: 100%; padding: .8rem; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .fit-product:hover { border-color: rgba(255, 101, 0, .45); box-shadow: 0 8px 22px rgba(24, 30, 48, .08); transform: translateY(-2px); }
    .fit-product.is-selected { background: rgba(25, 135, 84, .035); border-color: rgba(25, 135, 84, .4); }
    .fit-product-select { cursor: pointer; height: 1.2rem; width: 1.2rem; }
    .fit-product-select:checked { background-color: #198754; border-color: #198754; }
    .fit-product-image { align-items: center; background: #f5f6f8; border-radius: 10px; display: flex; flex: 0 0 74px; height: 82px; justify-content: center; overflow: hidden; width: 74px; }
    .fit-product-image img { height: 100%; object-fit: contain; padding: 4px; width: 100%; }
    .fit-product-name { color: #252a38; font-size: .84rem; font-weight: 800; line-height: 1.25; }
    .fit-product-sku { color: #8a92a1; font-size: .69rem; }
    .fit-chip { background: #f2f3f6; border-radius: 999px; color: #5e6675; display: inline-flex; font-size: .65rem; font-weight: 700; padding: .28rem .48rem; }
    .fit-chip.selected { background: rgba(25, 135, 84, .1); color: #137046; }
    .group-empty { color: #98a0ad; font-size: .78rem; padding: 1.35rem; text-align: center; }
    .filter-label { color: #6d7583; font-size: .68rem; font-weight: 800; letter-spacing: .05em; margin-bottom: .35rem; text-transform: uppercase; }
    .fit-config-status { border-radius: 99px; font-size: .64rem; font-weight: 800; padding: .3rem .5rem; white-space: nowrap; }
    .fit-config-status.pending { background: #fff0df; color: #985000; }
    .fit-config-status.done { background: #e3f5eb; color: #137046; }
    .fit-configure-button { border-radius: 8px; font-size: .7rem; white-space: nowrap; }
    .fit-product-select:disabled { cursor: not-allowed; }
    .fit-color-tabs { display: flex; gap: 8px; overflow-x: auto; padding: 3px 2px 10px; }
    .fit-color-tab { background: #f5f6f8; border: 1px solid #e1e5eb; border-radius: 9px; color: #5e6675; padding: 9px 14px; white-space: nowrap; font-size: .8rem; font-weight: 700; }
    .fit-color-tab[aria-selected="true"] { background: #fff1e7; border-color: #e8500a; color: #b83d00; }
    .fit-color-tab:focus-visible { outline: 3px solid #171b2d; outline-offset: 1px; }
    .fit-color-tab .color-state { margin-left: 7px; color: #9299a6; }
    .fit-color-tab.is-complete .color-state { color: #137046; }
    .fit-photo-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; max-height: 390px; overflow-y: auto; padding: 4px; }
    .fit-photo-option { cursor: pointer; position: relative; margin: 0; }
    .fit-photo-option input { position: absolute; top: 10px; right: 10px; z-index: 1; accent-color: #e8500a; }
    .fit-photo-tile { border: 2px solid #e5e8ee; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; padding: 10px; text-align: center; }
    .fit-photo-tile img { background: #f8f9fb; height: 132px; object-fit: contain; width: 100%; }
    .fit-photo-option input:checked + .fit-photo-tile { border-color: #e8500a; background: #fff4ec; box-shadow: 0 0 0 2px #e8500a20; }
    .fit-photo-option input:focus-visible + .fit-photo-tile { outline: 3px solid #171b2d; outline-offset: 2px; }
    .fit-photo-label { color: #6d7583; font-size: .75rem; margin-top: 7px; }
    #fit-presentation-modal .modal-content { border: 0; border-radius: 18px; overflow: hidden; }
    #fit-presentation-modal .modal-header { background: #171b2d; color: white; }
    @media (prefers-reduced-motion: reduce) { .fit-product, .modal.fade .modal-dialog { transition: none; } }
    @media (max-width: 575.98px) { .fit-photo-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767.98px) { .fit-product-image { flex-basis: 62px; height: 70px; width: 62px; } }
</style>
@endpush

@section('content')
<div class="fit-config-shell">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-person-bounding-box text-warning me-2"></i>AI Fit &amp; Go</h4>
            <p class="text-muted mb-0">Atur katalog produk yang digunakan pada pengalaman virtual fitting EIGER.</p>
        </div>
        @if($currentTab !== 'kiosks')
            <a href="{{ route('admin.products.index', ['ai_status' => 1]) }}" class="btn btn-outline-primary fw-semibold">
                <i class="bi bi-check2-square me-1"></i>Kelola Pilihan di List Product
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5 me-2"></i><div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <ul class="nav config-tabs border-bottom mb-4" role="tablist">
        <li class="nav-item">
            <a href="{{ route('admin.fit-and-go.index', ['tab' => 'products']) }}" class="nav-link {{ $currentTab === 'products' ? 'active' : '' }}">
                <i class="bi bi-grid-fill me-1"></i>Config Product
                <span class="badge rounded-pill bg-secondary ms-1">{{ $aiProducts->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.fit-and-go.index', ['tab' => 'kiosks']) }}" class="nav-link {{ $currentTab === 'kiosks' ? 'active' : '' }}">
                <i class="bi bi-display me-1"></i>Konfig Kiosk
                <span class="badge rounded-pill bg-secondary ms-1">{{ $kioskDevices->count() }}</span>
            </a>
        </li>
    </ul>

    @if($currentTab === 'products')
        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-7">
                <div class="fit-summary h-100">
                    <div class="d-flex align-items-start gap-3 position-relative" style="z-index:1">
                        <div class="summary-icon"><i class="bi bi-stars fs-5"></i></div>
                        <div>
                            <div class="fit-summary-label">Katalog {{ $selectedDevice?->name ?? 'AI Fit & Go' }}</div>
                            <div class="fit-summary-value mt-1">{{ $configuredProductCount }} produk dikonfigurasi</div>
                            <div class="small mt-2" style="color:#c8cedb">
                                {{ $selectionMode === 'latest'
                                    ? 'Mode Terbaru memilih otomatis maksimal 30 produk terbaru yang sudah Config Done.'
                                    : 'Mode Terpilih memakai produk yang dicentang khusus untuk perangkat ini.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="card fit-config-card h-100">
                    <div class="card-body">
                        <div class="filter-label">Wahana / Perangkat per Lantai</div>
                        @forelse($wahanaOptions as $wahana)
                            <a href="{{ route('admin.fit-and-go.index', ['tab' => 'products', 'device_id' => $wahana->id]) }}"
                               class="wahana-pill text-decoration-none text-dark {{ $selectedDevice?->id === $wahana->id ? 'active' : '' }} {{ !$loop->last ? 'mb-2' : '' }}">
                                <span class="icon"><i class="bi bi-display"></i></span>
                                <div class="min-width-0 flex-grow-1">
                                    <div class="fw-bold small">{{ $wahana->location ?: $wahana->name }}</div>
                                    <div class="text-muted" style="font-size:.7rem">{{ $wahana->name }} · {{ $wahana->device_code }}</div>
                                </div>
                                @if($selectedDevice?->id === $wahana->id)<i class="bi bi-check-circle-fill text-primary"></i>@endif
                            </a>
                        @empty
                            <div class="wahana-pill">
                                <span class="icon"><i class="bi bi-exclamation-circle"></i></span>
                                <div><div class="fw-bold small">Belum ada perangkat per lantai</div><div class="text-muted" style="font-size:.7rem">Tambahkan data perangkat sebelum mengatur katalog.</div></div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @if($selectedDevice)
            <form method="POST" action="{{ route('admin.fit-and-go.product-config.update', $selectedDevice) }}" id="fit-product-config-form">
                @csrf @method('PUT')
                <div class="card fit-config-card mb-4">
                    <div class="card-body p-3 p-lg-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                            <div>
                                <h6 class="fw-bold mb-1">Mode Pemilihan Produk</h6>
                                <div class="text-muted small">Pilih bagaimana katalog untuk {{ $selectedDevice->location ?: $selectedDevice->name }} dibentuk.</div>
                            </div>
                            <div class="input-group" style="max-width:320px">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input id="fit-product-search" type="search" class="form-control" placeholder="Cari nama atau SKU">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <label class="mode-option">
                                    <input type="radio" name="selection_mode" value="latest" @checked($selectionMode === 'latest')>
                                    <span class="mode-option-body d-flex gap-3">
                                        <span class="group-title-icon flex-shrink-0"><i class="bi bi-clock-history"></i></span>
                                    <span class="flex-grow-1"><span class="d-flex justify-content-between"><strong>Terbaru</strong><i class="bi bi-check-circle-fill mode-option-check"></i></span><span class="d-block small text-muted mt-1">Otomatis menampilkan maksimal 30 produk terbaru yang sudah memilih foto dan mengisi deskripsi.</span></span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label class="mode-option">
                                    <input type="radio" name="selection_mode" value="selected" @checked($selectionMode === 'selected')>
                                    <span class="mode-option-body d-flex gap-3">
                                        <span class="group-title-icon flex-shrink-0"><i class="bi bi-check2-square"></i></span>
                                        <span class="flex-grow-1"><span class="d-flex justify-content-between"><strong>Terpilih</strong><i class="bi bi-check-circle-fill mode-option-check"></i></span><span class="d-block small text-muted mt-1">Pilih manual produk khusus untuk perangkat/lantai ini, maksimal 30 produk.</span></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div><h6 class="fw-bold mb-1">Produk untuk {{ $selectedDevice->location ?: $selectedDevice->name }}</h6><div class="text-muted small">Pilih foto dan isi deskripsi melalui tombol Konfigurasi sebelum mencentang produk.</div></div>
                    <span class="fit-chip selected" id="fit-selected-count"><i class="bi bi-check-circle-fill me-1"></i>{{ $configuredProductCount }} Dipakai</span>
                </div>

                <div class="d-grid gap-3" id="fit-product-groups">
                    @foreach($productGroups as $group)
                        @php
                            $groupSelectedCount = $group['products']->whereIn('id', $selectedProductIds)->whereIn('id', $configuredProductIds)->count();
                            $groupLatestCount = $group['products']->whereIn('id', $latestProductIds)->count();
                        @endphp
                        <section class="group-card">
                            <div class="group-header">
                                <div class="group-title">
                                    <span class="group-title-icon"><i class="bi {{ $group['icon'] }}"></i></span>
                                    <div><div class="fw-bold">{{ $group['label'] }}</div><div class="text-muted" style="font-size:.7rem"><span class="group-selected-count">{{ $selectionMode === 'latest' ? $groupLatestCount : $groupSelectedCount }}</span> dari {{ $group['products']->count() }} produk dipakai</div></div>
                                </div>
                                <span class="fit-chip">Mapping PIM</span>
                            </div>
                            @if($group['products']->isEmpty())
                                <div class="group-empty"><i class="bi bi-box d-block fs-4 mb-1"></i>Belum ada produk aktif pada kelompok ini.</div>
                            @else
                                <div class="p-3"><div class="row g-3">
                                    @foreach($group['products'] as $product)
                                        @php
                                            $isConfigured = in_array($product->id, $configuredProductIds, true);
                                            $isSelected = $isConfigured && in_array($product->id, $selectedProductIds, true);
                                            $isLatest = in_array($product->id, $latestProductIds, true);
                                            $previewImage = \App\Support\PimMediaUrl::toPublicUrl($isConfigured ? $presentation->primaryImage($product) : $product->image);
                                            $categoryName = $product->atomSubCategory?->name ?? $product->atomCategory?->name ?? $product->category;
                                            $activityNames = collect($product->activities)->map(fn ($activity) => data_get($activity, 'master_activity.name') ?: data_get($activity, 'name'))->filter()->unique()->take(2);
                                        @endphp
                                        <div class="col-12 col-lg-6 col-xxl-4 fit-product-wrapper" data-search="{{ Str::lower($product->name.' '.$product->sku.' '.$categoryName) }}">
                                            <div class="fit-product d-flex gap-3 {{ ($selectionMode === 'latest' && $isLatest) || ($selectionMode === 'selected' && $isSelected) ? 'is-selected' : '' }}" data-auto-selected="{{ $isLatest ? 1 : 0 }}" data-configured="{{ $isConfigured ? 1 : 0 }}" data-rank="{{ $aiProducts->search(fn ($item) => $item->id === $product->id) }}" data-product-id="{{ $product->id }}">
                                                <div class="fit-product-image">
                                                    @if($previewImage)<img src="{{ $previewImage }}" alt="{{ $product->name }}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='block';"><i class="bi bi-image text-muted" style="display:none"></i>@else<i class="bi bi-image text-muted"></i>@endif
                                                </div>
                                                <div class="d-flex flex-column min-width-0 flex-grow-1">
                                                    <div class="d-flex justify-content-between gap-2">
                                                        <div class="min-width-0"><div class="fit-product-name text-truncate" id="fit-product-name-{{ $product->id }}" title="{{ $product->name }}">{{ $product->name }}</div><div class="fit-product-sku font-monospace mt-1">{{ $product->sku }}</div></div>
                                                        <input class="form-check-input fit-product-select" type="checkbox" name="product_ids[]" value="{{ $product->id }}" aria-labelledby="fit-product-name-{{ $product->id }}" @checked($isSelected) @disabled(!$isConfigured || $selectionMode === 'latest')>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-1 mt-2"><span class="fit-chip">{{ $categoryName ?: 'Tanpa kategori' }}</span>@foreach($activityNames as $activityName)<span class="fit-chip">{{ $activityName }}</span>@endforeach</div>
                                                    <div class="small mt-auto pt-2"><span class="fw-bold">{{ $product->variants_count }}</span> <span class="text-muted">varian</span><span class="text-muted mx-1">•</span><span class="fw-bold">{{ number_format($product->stock) }}</span> <span class="text-muted">stok</span></div>
                                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                                                        <span class="fit-config-status {{ $isConfigured ? 'done' : 'pending' }}" aria-live="polite">{{ $isConfigured ? 'Config Done' : 'Not Configure' }}</span>
                                                        <button type="button" class="btn btn-sm btn-outline-primary fit-configure-button" data-url="{{ route('admin.fit-and-go.product-presentation.show', $product) }}" aria-label="Konfigurasi foto dan deskripsi {{ $product->name }}"><i class="bi bi-sliders me-1"></i>Konfigurasi</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div></div>
                            @endif
                        </section>
                    @endforeach
                </div>

                <div class="card fit-config-card mt-4"><div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div><div class="fw-bold">Simpan konfigurasi {{ $selectedDevice->name }}</div><div class="small text-muted">Perubahan hanya berlaku untuk perangkat di {{ $selectedDevice->location ?: 'lokasi ini' }}.</div></div>
                    <button class="btn btn-eiger fw-bold px-4"><i class="bi bi-check-lg me-1"></i>Simpan Config Product</button>
                </div></div>
            </form>
        @else
            <div class="card fit-config-card"><div class="card-body text-center py-5"><i class="bi bi-display fs-1 text-muted"></i><h6 class="fw-bold mt-3">Belum ada device AI Fit &amp; Go</h6><p class="text-muted small mb-0">Konfigurasi produk membutuhkan data perangkat per lantai.</p></div></div>
        @endif

        <div class="modal fade" id="fit-presentation-modal" tabindex="-1" aria-labelledby="fit-presentation-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><h5 class="modal-title fw-bold" id="fit-presentation-title">Konfigurasi Produk AI Fit &amp; Go</h5><div class="small opacity-75 mt-1" id="fit-presentation-product"></div></div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div id="fit-presentation-loading" class="text-center text-muted py-5" role="status"><span class="spinner-border spinner-border-sm me-2"></span>Memuat galeri produk...</div>
                        <div class="alert alert-danger d-none" id="fit-presentation-error" role="alert"></div>
                        <form id="fit-presentation-form" class="d-none">
                            <div class="d-flex justify-content-between gap-2 mb-2"><span class="fw-bold">Varian Warna</span><span class="small text-muted" id="fit-color-progress" aria-live="polite"></span></div>
                            <div class="fit-color-tabs" id="fit-presentation-colors" role="tablist" aria-label="Varian warna produk"></div>
                            <div id="fit-color-panel" role="tabpanel">
                            <div class="fw-bold mb-2">Pilih Foto <span class="text-danger">*</span></div>
                            <div class="fit-photo-grid mb-3" id="fit-presentation-photos" role="radiogroup" aria-label="Foto produk untuk AI Fit & Go"></div>
                            <label for="fit-presentation-description" class="form-label fw-bold">Deskripsi Barang <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="fit-presentation-description" name="description" rows="4" maxlength="5000" required placeholder="Tulis deskripsi barang untuk AI Fit & Go..."></textarea>
                            <div class="d-flex justify-content-between gap-2 small text-muted mt-2"><span>Deskripsi mengikuti warna dan foto yang dipilih. Konfigurasi setiap warna berlaku di semua lantai.</span><span id="fit-description-count">0/5000</span></div>
                            </div>
                            <div class="small text-muted mt-3">Lengkapi foto dan deskripsi semua warna untuk mendapatkan status Config Done.</div>
                        </form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" form="fit-presentation-form" class="btn btn-eiger fw-bold" id="fit-presentation-save" disabled><i class="bi bi-check-lg me-1"></i>Simpan Konfigurasi</button></div>
                </div>
            </div>
        </div>

        @if($aiProducts->isEmpty())
            <div class="card fit-config-card mt-3"><div class="card-body text-center py-5"><h6 class="fw-bold">Belum ada produk AI Fit &amp; Go</h6><p class="text-muted small mb-3">Aktifkan status AI Product pada halaman List Product agar produknya muncul di sini.</p><a href="{{ route('admin.products.index') }}" class="btn btn-primary">Buka List Product</a></div></div>
        @endif
    @endif
    @if($currentTab === 'kiosks')
        <div class="card fit-config-card overflow-hidden">
            <div class="card-header bg-white py-3 px-3 px-lg-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h6 class="fw-bold mb-1"><i class="bi bi-display text-primary me-2"></i>Perangkat Kiosk per Lantai</h6>
                    <div class="text-muted small">Tiga kiosk lantai bersifat tetap. Konfigurasi perangkat hanya mengubah kode aktivasi.</div>
                </div>
                <span class="fit-chip selected">{{ $kioskDevices->where('is_active', true)->count() }} perangkat aktif</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Perangkat</th><th>Slug URL</th><th>Lokasi</th><th>Keamanan Aktivasi</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    @forelse($kioskDevices as $device)
                        <tr>
                            <td><div class="d-flex align-items-center gap-2"><span class="group-title-icon"><i class="bi bi-display"></i></span><div><div class="fw-bold">{{ $device->name }}</div><div class="small text-muted">ID #{{ $device->id }}</div></div></div></td>
                            <td><code>/fit-and-go/{{ $device->device_code }}</code></td>
                            <td><span class="fit-chip"><i class="bi bi-geo-alt-fill me-1"></i>{{ $device->location ?: 'Belum ditentukan' }}</span></td>
                            <td>
                                @if($device->activation_code_hash)
                                    <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-shield-check me-1"></i>Kode Aktivasi Aktif</span>
                                    <div class="small text-muted mt-1">{{ $device->device_token_hash ? 'Perangkat sudah dipasangkan' : 'Menunggu pairing perangkat' }}</div>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-warning"><i class="bi bi-shield-exclamation me-1"></i>Belum dikonfigurasi</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $device->is_active ? 'badge-success-soft' : 'bg-secondary' }}">{{ $device->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end"><a href="{{ route('admin.fit-and-go.devices.edit', $device) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders me-1"></i>Konfigurasi</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">Slot kiosk statis belum tersedia. Jalankan migrasi database terbaru.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('fit-product-config-form');
    if (!form) return;

    const modeInputs = [...form.querySelectorAll('input[name="selection_mode"]')];
    const productInputs = [...form.querySelectorAll('.fit-product-select')];
    const counter = document.getElementById('fit-selected-count');
    const search = document.getElementById('fit-product-search');
    const cards = [...form.querySelectorAll('.fit-product')];
    const modalElement = document.getElementById('fit-presentation-modal');
    const modalForm = document.getElementById('fit-presentation-form');
    const modalError = document.getElementById('fit-presentation-error');
    const modalLoading = document.getElementById('fit-presentation-loading');
    const modalPhotos = document.getElementById('fit-presentation-photos');
    const modalDescription = document.getElementById('fit-presentation-description');
    const modalSave = document.getElementById('fit-presentation-save');
    const modalColors = document.getElementById('fit-presentation-colors');
    let colors = [];
    let activeColor = null;
    let activeCard = null;
    let presentationUrl = null;
    let galleryRequest = null;
    let saving = false;

    function showModalError(message) {
        modalError.textContent = message;
        modalError.classList.remove('d-none');
    }

    function rememberDescription() {
        if (activeColor?.image) {
            activeColor.description = modalDescription.value;
            activeColor.photo_descriptions[activeColor.image] = modalDescription.value;
        }
    }

    function colorComplete(color) {
        return color.photos.some(photo => photo.value === color.image && !photo.failed) && !!color.description.trim();
    }

    function updateSaveState() {
        document.getElementById('fit-description-count').textContent = `${modalDescription.value.length}/5000`;
        const completed = colors.filter(colorComplete).length;
        document.getElementById('fit-color-progress').textContent = `${completed}/${colors.length} warna siap`;
        [...modalColors.children].forEach((tab, index) => {
            const complete = colorComplete(colors[index]);
            tab.classList.toggle('is-complete', complete);
            tab.querySelector('.color-state').textContent = complete ? '✓' : '○';
        });
        modalSave.disabled = saving || !colors.length || completed !== colors.length;
        modalColors.querySelectorAll('button').forEach(button => button.disabled = saving);
        modalDescription.disabled = saving || !activeColor?.image;
        modalPhotos.querySelectorAll('input').forEach(input => input.disabled = saving || !!activeColor.photos.find(photo => photo.value === input.value)?.failed);
    }

    function openColor(color) {
        rememberDescription();
        activeColor = color;
        const index = colors.indexOf(color);
        [...modalColors.children].forEach((tab, tabIndex) => {
            tab.setAttribute('aria-selected', String(tabIndex === index));
            tab.tabIndex = tabIndex === index ? 0 : -1;
        });
        document.getElementById('fit-color-panel').setAttribute('aria-labelledby', `fit-color-${index}`);
        modalPhotos.setAttribute('aria-label', `Foto warna ${color.name} untuk AI Fit & Go`);
        modalPhotos.replaceChildren();
        color.photos.forEach((photo, photoIndex) => {
            const label = document.createElement('label'); label.className = 'fit-photo-option';
            const input = document.createElement('input');
            input.type = 'radio'; input.name = 'image'; input.value = photo.value;
            input.checked = photo.value === color.image; input.required = true;
            input.setAttribute('aria-label', `Foto ${photoIndex + 1}`);
            const tile = document.createElement('span'); tile.className = 'fit-photo-tile';
            const image = document.createElement('img'); image.src = photo.url;
            image.alt = `${color.name} - Foto ${photoIndex + 1}`; image.loading = 'lazy';
            const caption = document.createElement('span'); caption.className = 'fit-photo-label';
            caption.textContent = `Foto ${photoIndex + 1}`;
            image.addEventListener('error', () => {
                photo.failed = true; input.disabled = true; input.checked = false;
                caption.textContent = 'Foto tidak dapat dimuat'; updateSaveState();
            });
            tile.append(image, caption); label.append(input, tile); modalPhotos.append(label);
        });
        modalDescription.value = color.description;
        modalDescription.placeholder = `Tulis deskripsi warna ${color.name} untuk foto yang dipilih...`;
        modalError.classList.add('d-none');
        if (!color.photos.length) showModalError(`Warna ${color.name} belum memiliki foto. Tunggu foto dari PIM sebelum melakukan konfigurasi.`);
        updateSaveState();
    }

    function refresh() {
        const latestMode = form.querySelector('input[name="selection_mode"]:checked')?.value === 'latest';
        const latestCards = cards.filter(card => card.dataset.configured === '1')
            .sort((a, b) => Number(a.dataset.rank) - Number(b.dataset.rank)).slice(0, 30);
        cards.forEach(card => card.dataset.autoSelected = latestCards.includes(card) ? '1' : '0');
        productInputs.forEach(input => {
            const card = input.closest('.fit-product');
            input.disabled = latestMode || card.dataset.configured !== '1';
            if (card.dataset.configured !== '1') input.checked = false;
            card.classList.toggle('is-selected', latestMode ? card.dataset.autoSelected === '1' : input.checked);
        });

        form.querySelectorAll('.group-card').forEach(group => {
            const cards = [...group.querySelectorAll('.fit-product')];
            const used = cards.filter(card => latestMode ? card.dataset.autoSelected === '1' : card.querySelector('.fit-product-select').checked).length;
            const groupCounter = group.querySelector('.group-selected-count');
            if (groupCounter) groupCounter.textContent = used;
        });

        const count = productInputs.filter(input => latestMode
            ? input.closest('.fit-product').dataset.autoSelected === '1'
            : input.checked).length;
        counter.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>${count} Dipakai`;
    }

    productInputs.forEach(input => input.addEventListener('change', () => {
        const selectedCount = productInputs.filter(item => item.checked).length;
        if (selectedCount > 30) {
            input.checked = false;
            window.alert('Maksimal 30 produk dapat dipilih untuk satu perangkat.');
        }
        refresh();
    }));
    modeInputs.forEach(input => input.addEventListener('change', refresh));
    search?.addEventListener('input', event => {
        const keyword = event.target.value.toLowerCase().trim();
        form.querySelectorAll('.fit-product-wrapper').forEach(wrapper => {
            wrapper.classList.toggle('d-none', !wrapper.dataset.search.includes(keyword));
        });
    });

    form.querySelectorAll('.fit-configure-button').forEach(button => button.addEventListener('click', async () => {
        activeCard = button.closest('.fit-product');
        presentationUrl = button.dataset.url;
        modalForm.classList.add('d-none');
        modalError.classList.add('d-none');
        modalLoading.classList.remove('d-none');
        modalPhotos.replaceChildren();
        modalColors.replaceChildren(); colors = []; activeColor = null;
        modalDescription.value = '';
        modalSave.disabled = true;
        document.getElementById('fit-presentation-product').textContent = activeCard.querySelector('.fit-product-name').textContent;
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
        galleryRequest?.abort();
        const request = new AbortController();
        galleryRequest = request;
        try {
            const response = await fetch(presentationUrl, { headers: { Accept: 'application/json' }, signal: request.signal });
            if (!response.ok) throw new Error('Galeri tidak dapat dimuat. Tutup popup lalu coba kembali.');
            const data = await response.json();
            if (galleryRequest !== request || request.signal.aborted) return;
            colors = data.colors.map(color => ({ ...color, photo_descriptions: { ...color.photo_descriptions } }));
            colors.forEach((color, index) => {
                const tab = document.createElement('button');
                tab.type = 'button'; tab.className = 'fit-color-tab'; tab.id = `fit-color-${index}`;
                tab.setAttribute('role', 'tab'); tab.setAttribute('aria-controls', 'fit-color-panel');
                const name = document.createElement('span'); name.textContent = color.name;
                const state = document.createElement('span'); state.className = 'color-state'; state.setAttribute('aria-hidden', 'true');
                tab.append(name, state); tab.addEventListener('click', () => openColor(color));
                tab.addEventListener('keydown', event => {
                    let next;
                    if (event.key === 'ArrowRight') next = (index + 1) % colors.length;
                    if (event.key === 'ArrowLeft') next = (index + colors.length - 1) % colors.length;
                    if (event.key === 'Home') next = 0;
                    if (event.key === 'End') next = colors.length - 1;
                    if (next !== undefined) { event.preventDefault(); openColor(colors[next]); modalColors.children[next].focus(); }
                });
                modalColors.append(tab);
            });
            modalForm.classList.remove('d-none');
            openColor(colors[0]);
        } catch (error) {
            if (error.name !== 'AbortError') showModalError(error.message);
        } finally {
            if (galleryRequest === request) modalLoading.classList.add('d-none');
        }
    }));
    modalPhotos.addEventListener('change', event => {
        rememberDescription();
        activeColor.image = event.target.value;
        activeColor.description = activeColor.photo_descriptions[activeColor.image] || '';
        modalDescription.value = activeColor.description;
        updateSaveState();
    });
    modalDescription.addEventListener('input', () => { rememberDescription(); updateSaveState(); });
    modalElement.addEventListener('hide.bs.modal', event => {
        if (saving) event.preventDefault();
        else galleryRequest?.abort();
    });
    modalForm.addEventListener('submit', async event => {
        event.preventDefault();
        rememberDescription();
        if (!colors.length || !colors.every(colorComplete) || saving) return;
        saving = true; updateSaveState(); modalError.classList.add('d-none');
        modalSave.textContent = 'Menyimpan...';
        try {
            const response = await fetch(presentationUrl, {
                method: 'PUT', headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify({ colors: colors.map(({key, image, description, photo_descriptions}) => ({key, image, description, photo_descriptions})) })
            });
            if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Sesi berakhir. Muat ulang halaman dan login kembali.');
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Konfigurasi gagal disimpan.');
            activeCard.dataset.configured = '1';
            const status = activeCard.querySelector('.fit-config-status');
            status.textContent = 'Config Done'; status.classList.replace('pending', 'done');
            const preview = activeCard.querySelector('.fit-product-image');
            const image = document.createElement('img');
            image.src = colors[0].photos.find(photo => photo.value === colors[0].image).url;
            image.alt = activeCard.querySelector('.fit-product-name').textContent;
            preview.replaceChildren(image);
            refresh(); saving = false;
            bootstrap.Modal.getInstance(modalElement).hide();
        } catch (error) {
            showModalError(error.message);
        } finally {
            saving = false; modalSave.textContent = 'Simpan Konfigurasi'; updateSaveState();
        }
    });

    refresh();
});
</script>
@endpush
