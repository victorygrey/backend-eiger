@extends('layouts.admin')

@section('title', 'Detail Produk')
@section('page-title', 'Detail Produk')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}" class="text-decoration-none">Products</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@push('styles')
<style>
    .detail-card { border: 0; border-radius: 14px; box-shadow: 0 10px 30px rgba(26, 31, 44, .07); }
    .detail-label { color: #7b8494; font-size: .68rem; font-weight: 700; letter-spacing: .055em; margin-bottom: .3rem; text-transform: uppercase; }
    .detail-value { color: #202535; font-weight: 650; overflow-wrap: anywhere; }
    .detail-field { background: #f8f9fb; border: 1px solid #e8ebf0; border-radius: 10px; min-height: 72px; padding: .85rem 1rem; }
    .product-cover { align-items: center; background: #f7f8fa; border: 1px solid #e5e7eb; border-radius: 12px; display: flex; height: 310px; justify-content: center; overflow: hidden; }
    .product-cover img { height: 100%; object-fit: contain; padding: 10px; width: 100%; }
    .gallery-item { align-items: center; background: #fff; border: 1px solid #e5e7eb; border-radius: 9px; display: flex; height: 78px; justify-content: center; overflow: hidden; }
    .gallery-item img { height: 100%; object-fit: contain; padding: 4px; width: 100%; }
    .section-title { align-items: center; color: #202535; display: flex; font-size: .82rem; font-weight: 800; gap: .5rem; letter-spacing: .025em; margin-bottom: 1rem; text-transform: uppercase; }
    .section-title i { color: #ff6a00; }
    .data-item { background: #f8f9fb; border: 1px solid #e8ebf0; border-radius: 10px; height: 100%; padding: .85rem; }
    .rating-pill { background: #fff3cd; border: 1px solid #ffe69c; border-radius: 999px; color: #805b00; font-size: .72rem; font-weight: 800; padding: .3rem .55rem; white-space: nowrap; }
    .attribute-card { background: linear-gradient(145deg, #fff, #fafbfc); border: 1px solid #e7eaf0; border-radius: 12px; height: 100%; overflow: hidden; padding: 1rem; position: relative; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .attribute-card::before { background: #ff6a00; content: ''; inset: 0 auto 0 0; position: absolute; width: 3px; }
    .attribute-card:hover { border-color: rgba(255, 106, 0, .35); box-shadow: 0 8px 20px rgba(26, 31, 44, .07); transform: translateY(-1px); }
    .attribute-icon { align-items: center; background: rgba(255, 106, 0, .1); border-radius: 9px; color: #e85f00; display: inline-flex; flex: 0 0 auto; font-size: 1rem; height: 36px; justify-content: center; width: 36px; }
    .attribute-name { color: #252a38; font-size: .82rem; font-weight: 800; line-height: 1.25; }
    .attribute-code { color: #8b93a1; font-family: var(--bs-font-monospace); font-size: .65rem; overflow-wrap: anywhere; }
    .attribute-value { color: #4d5564; font-size: .82rem; line-height: 1.65; overflow-wrap: anywhere; }
    .attribute-tag { background: #f2f4f7; border: 1px solid #e1e5ea; border-radius: 999px; color: #4b5563; display: inline-flex; font-size: .72rem; font-weight: 650; padding: .35rem .6rem; }
    .attribute-master { background: rgba(25, 135, 84, .07); border: 1px solid rgba(25, 135, 84, .18); border-radius: 9px; padding: .75rem; }
    .attribute-master-name { color: #176b46; font-size: .88rem; font-weight: 800; }
    .attribute-master-meta { color: #718078; font-size: .68rem; }
    .attribute-media { align-items: center; background: #f4f6f8; border: 1px solid #e5e7eb; border-radius: 9px; display: flex; height: 150px; justify-content: center; overflow: hidden; }
    .attribute-media img, .attribute-media video { height: 100%; object-fit: contain; width: 100%; }
    .media-preview { align-items: center; background: #f7f8fa; border-radius: 8px; display: flex; height: 150px; justify-content: center; overflow: hidden; }
    .media-preview img, .media-preview video { height: 100%; object-fit: contain; width: 100%; }
    .variant-table { min-width: 880px; }
    .variant-table thead th { background: #171b2d; color: #ff6a00; border: 0; font-size: .7rem; letter-spacing: .05em; padding: .85rem; text-transform: uppercase; }
    .variant-table td { padding: .85rem; vertical-align: middle; }
    .variant-thumb { align-items: center; background: #f7f8fa; border: 1px solid #e5e7eb; border-radius: 7px; display: flex; height: 48px; justify-content: center; overflow: hidden; width: 48px; }
    .variant-thumb img { height: 100%; object-fit: contain; width: 100%; }
    .readonly-notice { background: rgba(13, 110, 253, .07); border: 1px solid rgba(13, 110, 253, .18); border-radius: 10px; color: #315b90; }
    .json-payload { background: #171b2d; border-radius: 10px; color: #d8dee9; font-size: .76rem; max-height: 420px; overflow: auto; padding: 1rem; white-space: pre-wrap; word-break: break-word; }
</style>
@endpush

@section('content')
@php
    $plainText = static function ($value): string {
        $html = preg_replace('~<br\s*/?>|</(?:p|div|li|h[1-6])>~i', ' ', (string) $value);
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    };
    $formatRating = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $mediaKind = static function ($media): string {
        $type = strtolower((string) ($media['type'] ?? ''));
        $mime = strtolower((string) ($media['mime'] ?? ''));
        if ($type === 'image' || str_starts_with($mime, 'image/')) return 'image';
        if ($type === 'video' || str_starts_with($mime, 'video/')) return 'video';
        $path = strtolower((string) parse_url((string) ($media['url'] ?? ''), PHP_URL_PATH));
        if (preg_match('/\.(?:jpe?g|png|webp|gif|svg)$/', $path)) return 'image';
        if (preg_match('/\.(?:mp4|webm|ogg)$/', $path)) return 'video';
        return 'link';
    };
    $attributePresentation = static function (array $attribute) use ($plainText): array {
        $code = trim((string) ($attribute['attributeCode'] ?? 'attribute'));
        $rawValue = $attribute['value'] ?? '';
        $decoded = is_string($rawValue) ? json_decode($rawValue, true) : null;
        $value = $plainText(is_array($decoded) ? implode(', ', array_filter($decoded, 'is_scalar')) : $rawValue);
        $normalizedCode = strtolower($code);
        $label = ucwords(str_replace(['_', '-'], ' ', $code));
        $icon = match (true) {
            str_contains($normalizedCode, 'description') => 'bi-card-text',
            str_contains($normalizedCode, 'tag') => 'bi-tags-fill',
            str_contains($normalizedCode, 'color') => 'bi-palette-fill',
            str_contains($normalizedCode, 'material') => 'bi-layers-fill',
            str_contains($normalizedCode, 'dimension'), str_contains($normalizedCode, 'length'), str_contains($normalizedCode, 'width'), str_contains($normalizedCode, 'height') => 'bi-rulers',
            str_contains($normalizedCode, 'water'), str_contains($normalizedCode, 'weather') => 'bi-droplet-fill',
            str_contains($normalizedCode, 'activity') => 'bi-activity',
            default => 'bi-tag-fill',
        };
        $url = filter_var($rawValue, FILTER_VALIDATE_URL) || str_starts_with((string) $rawValue, '/api/pim-media/') ? (string) $rawValue : '';
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $media = preg_match('/\.(?:jpe?g|png|webp|gif|svg)$/', $path) ? 'image'
            : (preg_match('/\.(?:mp4|webm|ogg)$/', $path) ? 'video' : ($url !== '' ? 'link' : null));
        $list = [];
        if ($media === null && (str_contains($normalizedCode, 'tag') || str_contains($normalizedCode, 'keyword'))) {
            $list = collect(preg_split('/[,;|]+/', $value))->map(fn ($item) => trim($item))->filter()->unique()->values()->all();
        }

        return compact('code', 'label', 'icon', 'value', 'url', 'media', 'list');
    };
    $categoryName = $product->atomCategory?->name ?? $product->category;
    $subCategoryName = $product->atomSubCategory?->name;
    $mainImage = $product->image ?: ($availableImages[0] ?? null);
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <div>
            <h4 class="fw-bold mb-1">{{ $product->name }}</h4>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <code>{{ $product->sku }}</code>
                <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary"><i class="bi bi-cloud-check-fill me-1"></i>Data PIM/CARE</span>
                @if($product->pim_catalog_active)<span class="badge rounded-pill bg-success bg-opacity-10 text-success">Katalog Aktif</span>@endif
            </div>
        </div>
    </div>
    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus produk {{ addslashes($product->name) }} ({{ $product->sku }}) dari katalog CMS?')">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger"><i class="bi bi-trash-fill me-1"></i>Hapus Produk</button>
    </form>
</div>

<div class="readonly-notice px-3 py-2 mb-4 small">
    <i class="bi bi-info-circle-fill me-2"></i>Detail ini hanya dapat dibaca. Master data produk diperbarui melalui integrasi PIM dan data harga serta stok melalui CARE.
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card detail-card h-100">
            <div class="card-body p-3 p-lg-4">
                <div class="product-cover mb-3">
                    @if($mainImage)
                        <img src="{{ $mainImage }}" alt="{{ $product->name }}">
                    @else
                        <div class="text-center text-muted"><i class="bi bi-image fs-1 d-block"></i>Belum ada foto</div>
                    @endif
                </div>
                @if(count($availableImages) > 1)
                    <div class="row g-2">
                        @foreach(array_slice($availableImages, 0, 8) as $image)
                            <div class="col-3"><a class="gallery-item" href="{{ $image }}" target="_blank" rel="noopener"><img src="{{ $image }}" alt="Galeri {{ $product->name }}" loading="lazy"></a></div>
                        @endforeach
                    </div>
                    @if(count($availableImages) > 8)<div class="small text-muted mt-2">+{{ count($availableImages) - 8 }} foto lainnya tersedia pada media PIM.</div>@endif
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card detail-card h-100">
            <div class="card-body p-3 p-lg-4">
                <div class="section-title"><i class="bi bi-info-square-fill"></i>Informasi Produk</div>
                <div class="row g-3">
                    <div class="col-md-6"><div class="detail-field"><div class="detail-label">Nama Produk</div><div class="detail-value">{{ $product->name }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">SKU Generic</div><div class="detail-value font-monospace">{{ $product->sku }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Harga CARE</div><div class="detail-value">Rp {{ number_format((float) $product->price, 0, ',', '.') }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Total Stok</div><div class="detail-value">{{ number_format($product->stock) }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Category</div><div class="detail-value">{{ $categoryName ?: '—' }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Subcategory</div><div class="detail-value">{{ $subCategoryName ?: '—' }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Zone</div><div class="detail-value">{{ $product->zone?->name ?: '—' }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Material</div><div class="detail-value">{{ $product->material ?: '—' }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Gender</div><div class="detail-value">{{ $product->gender ?: '—' }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Product Group</div><div class="detail-value">{{ $product->product_group ?: '—' }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Berat</div><div class="detail-value">{{ $product->weight !== null ? $formatRating($product->weight).' gram' : '—' }}</div></div></div>
                    <div class="col-md-3"><div class="detail-field"><div class="detail-label">Jumlah Varian</div><div class="detail-value">{{ $product->variants->count() }}</div></div></div>
                </div>
                <hr class="my-4">
                <div class="section-title mb-2"><i class="bi bi-card-text"></i>Deskripsi</div>
                <p class="text-muted mb-0" style="line-height:1.75">{{ $plainText($product->description) ?: 'Belum ada deskripsi produk.' }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card detail-card mt-4">
    <div class="card-body p-3 p-lg-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
            <div class="section-title mb-0"><i class="bi bi-broadcast-pin"></i>Status Digital Store</div>
            <small class="text-muted">Pengaturan status dilakukan dari halaman List Product.</small>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><div class="detail-field d-flex align-items-center justify-content-between"><div><div class="detail-label">AI Product</div><div class="detail-value">AI Fit &amp; Go</div></div><span class="badge rounded-pill {{ $product->ai_fit_and_go_active ? 'bg-success' : 'bg-secondary' }}">{{ $product->ai_fit_and_go_active ? 'Active' : 'Not Active' }}</span></div></div>
            <div class="col-md-6"><div class="detail-field d-flex align-items-center justify-content-between"><div><div class="detail-label">Tablet</div><div class="detail-value">Interactive Tablet</div></div><span class="badge rounded-pill {{ $product->interactive_tablet_active ? 'bg-success' : 'bg-secondary' }}">{{ $product->interactive_tablet_active ? 'Active' : 'Not Active' }}</span></div></div>
        </div>
    </div>
</div>

<div class="row g-4 mt-0">
    <div class="col-xl-6">
        <div class="card detail-card h-100"><div class="card-body p-3 p-lg-4">
            <div class="section-title"><i class="bi bi-cpu-fill"></i>Teknologi Produk</div>
            <div class="row g-3">
                @forelse($product->technologies as $technology)
                    <div class="col-12"><div class="data-item d-flex gap-3">
                        @if(!empty($technology['image']))<img src="{{ $technology['image'] }}" alt="{{ $technology['name'] ?? 'Teknologi' }}" class="rounded border bg-white object-fit-contain flex-shrink-0" style="width:72px;height:72px">@endif
                        <div><div class="fw-bold">{{ $technology['name'] ?? 'Teknologi' }}</div><div class="small text-muted mt-1">{{ $plainText($technology['description'] ?? '') ?: 'Tidak ada deskripsi.' }}</div></div>
                    </div></div>
                @empty
                    <div class="col-12 text-muted">Belum ada data teknologi.</div>
                @endforelse
            </div>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="card detail-card h-100"><div class="card-body p-3 p-lg-4">
            <div class="section-title"><i class="bi bi-activity"></i>Aktivitas &amp; Rating</div>
            <div class="row g-3">
                @forelse($product->activities as $activity)
                    @php
                        $hasActivityRating = is_numeric($activity['selected'] ?? null) && !is_bool($activity['selected'] ?? null) && is_numeric($activity['rating'] ?? null) && (float) $activity['rating'] > 0;
                    @endphp
                    <div class="col-md-6"><div class="data-item">
                        <div class="d-flex align-items-start justify-content-between gap-2"><div class="fw-bold">{{ $activity['name'] ?? 'Aktivitas' }}</div>@if($hasActivityRating)<span class="rating-pill">{{ $formatRating($activity['selected']) }} / {{ $formatRating($activity['rating']) }}</span>@endif</div>
                        @if(!empty($activity['desc_rating']))<div class="small fw-semibold text-warning-emphasis mt-1">{{ $plainText($activity['desc_rating']) }}</div>@endif
                        <div class="small text-muted mt-1">{{ $plainText($activity['description'] ?? '') ?: 'Tidak ada deskripsi.' }}</div>
                    </div></div>
                @empty
                    <div class="col-12 text-muted">Belum ada data aktivitas.</div>
                @endforelse
            </div>
        </div></div>
    </div>
</div>

<div class="row g-4 mt-0">
    <div class="col-xl-6">
        <div class="card detail-card h-100"><div class="card-body p-3 p-lg-4">
            <div class="section-title"><i class="bi bi-speedometer2"></i>Performance</div>
            <div class="row g-3">
                @forelse($product->performances as $performance)
                    @php
                        $hasPerformanceRating = is_numeric($performance['selected'] ?? null) && !is_bool($performance['selected'] ?? null) && is_numeric($performance['rating'] ?? null) && (float) $performance['rating'] > 0;
                    @endphp
                    <div class="col-md-6"><div class="data-item">
                        <div class="d-flex align-items-start justify-content-between gap-2"><div class="fw-bold">{{ $performance['name'] ?? 'Performance' }}</div>@if($hasPerformanceRating)<span class="rating-pill">{{ $formatRating($performance['selected']) }} / {{ $formatRating($performance['rating']) }}</span>@endif</div>
                        @if(!empty($performance['desc_rating']))<div class="small fw-semibold text-warning-emphasis mt-1">{{ $plainText($performance['desc_rating']) }}</div>@endif
                        <div class="small text-muted mt-1">{{ $plainText($performance['description'] ?? '') ?: 'Tidak ada deskripsi.' }}</div>
                    </div></div>
                @empty
                    <div class="col-12 text-muted">Belum ada data performance.</div>
                @endforelse
            </div>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="card detail-card h-100"><div class="card-body p-3 p-lg-4">
            <div class="section-title"><i class="bi bi-rulers"></i>Spesifikasi Fisik</div>
            <div class="row g-3">
                @forelse($product->specifications as $specification)
                    <div class="col-md-6"><div class="data-item text-center"><div class="detail-label">{{ $specification['name'] ?? $specification['code'] ?? 'Spesifikasi' }}</div><div class="detail-value">{{ $specification['value'] ?? '—' }}{{ !empty($specification['unit']) ? ' '.$specification['unit'] : '' }}</div></div></div>
                @empty
                    <div class="col-12 text-muted">Belum ada data spesifikasi.</div>
                @endforelse
            </div>
        </div></div>
    </div>
</div>

<div class="card detail-card mt-4">
    <div class="card-body p-3 p-lg-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
            <div class="section-title mb-0"><i class="bi bi-tags-fill"></i>Custom Attributes</div>
            @if(count($customAttributes))<span class="badge rounded-pill bg-light text-dark border">{{ count($customAttributes) }} atribut dari PIM</span>@endif
        </div>
        <div class="row g-3">
            @forelse($customAttributes as $attribute)
                @php
                    $attributeView = $attributePresentation($attribute);
                    $wideAttribute = mb_strlen($attributeView['value']) > 140 || str_contains(strtolower($attributeView['code']), 'description');
                @endphp
                <div class="{{ $wideAttribute ? 'col-12' : 'col-md-6 col-xl-4' }}">
                    <div class="attribute-card">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <span class="attribute-icon"><i class="bi {{ $attributeView['icon'] }}"></i></span>
                            <div class="min-width-0">
                                <div class="attribute-name">{{ $attributeView['label'] }}</div>
                                <div class="attribute-code">{{ $attributeView['code'] }}</div>
                            </div>
                        </div>
                        @if(!empty($attribute['master_values']))
                            <div class="d-flex flex-column gap-2">
                                @foreach($attribute['master_values'] as $masterValue)
                                    <div class="attribute-master d-flex align-items-center justify-content-between gap-3">
                                        <div>
                                            <div class="attribute-master-name"><i class="bi bi-check-circle-fill me-1"></i>{{ $masterValue['name'] }}</div>
                                            <div class="attribute-master-meta">{{ $masterValue['type'] }}@if($masterValue['parent']) · {{ $masterValue['parent'] }}@endif</div>
                                        </div>
                                        <span class="badge bg-white text-secondary border font-monospace">{{ $masterValue['code'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif($attributeView['media'] === 'image')
                            <a href="{{ $attributeView['url'] }}" target="_blank" rel="noopener" class="attribute-media mb-2"><img src="{{ $attributeView['url'] }}" alt="{{ $attributeView['label'] }}" loading="lazy"></a>
                        @elseif($attributeView['media'] === 'video')
                            <div class="attribute-media mb-2"><video controls preload="metadata"><source src="{{ $attributeView['url'] }}"></video></div>
                        @elseif($attributeView['media'] === 'link')
                            <a href="{{ $attributeView['url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Buka tautan</a>
                        @elseif($attributeView['list'])
                            <div class="d-flex flex-wrap gap-2">@foreach($attributeView['list'] as $item)<span class="attribute-tag">{{ $item }}</span>@endforeach</div>
                        @else
                            <div class="attribute-value">{{ $attributeView['value'] ?: '—' }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="text-center text-muted border rounded-3 py-4"><i class="bi bi-tags d-block fs-3 mb-2"></i>Belum ada custom attributes dari PIM.</div></div>
            @endforelse
        </div>
    </div>
</div>

<div class="card detail-card mt-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="p-3 p-lg-4 pb-3"><div class="section-title mb-0"><i class="bi bi-diagram-3-fill"></i>Daftar Varian Produk</div></div>
        <div class="table-responsive">
            <table class="table variant-table mb-0">
                <thead><tr><th>Foto</th><th>SKU Varian</th><th>Nama</th><th>Warna</th><th>Ukuran</th><th>ECM SKU</th><th>MOQ</th><th class="text-end">Harga</th><th class="text-end">Stok</th></tr></thead>
                <tbody>
                    @forelse($product->variants as $variant)
                        <tr>
                            <td><div class="variant-thumb">@if($variant->image)<img src="{{ $variant->image }}" alt="{{ $variant->name }}" loading="lazy">@else<i class="bi bi-image text-muted"></i>@endif</div></td>
                            <td class="font-monospace fw-semibold">{{ $variant->sku }}</td>
                            <td>{{ $variant->name ?: '—' }}</td><td>{{ $variant->color ?: '—' }}</td><td>{{ $variant->size ?: '—' }}</td><td>{{ $variant->ecmsku ?: '—' }}</td><td>{{ $variant->moq ?: '—' }}</td>
                            <td class="text-end">Rp {{ number_format((float) $variant->price, 0, ',', '.') }}</td><td class="text-end fw-bold">{{ number_format($variant->stock) }}</td>
                        </tr>
                        @if($variant->custom_attributes)
                            <tr><td></td><td colspan="8" class="pt-0"><div class="small text-muted">@foreach($variant->custom_attributes as $variantAttribute)<span class="badge bg-light text-dark border me-1">{{ $variantAttribute['attributeCode'] }}: {{ $plainText($variantAttribute['value']) }}</span>@endforeach</div></td></tr>
                        @endif
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data varian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card detail-card mt-4">
    <div class="card-body p-3 p-lg-4">
        <div class="section-title"><i class="bi bi-play-btn-fill"></i>Media PIM</div>
        <div class="row g-3">
            @forelse($product->pim_media as $media)
                @php $kind = $mediaKind($media); $url = (string) ($media['url'] ?? ''); @endphp
                <div class="col-sm-6 col-xl-4"><div class="data-item">
                    <div class="media-preview mb-2">
                        @if($kind === 'image')<img src="{{ $url }}" alt="{{ $media['role'] ?? 'Media PIM' }}" loading="lazy">
                        @elseif($kind === 'video')<video controls preload="metadata"><source src="{{ $url }}" type="{{ $media['mime'] ?? '' }}"></video>
                        @else<i class="bi bi-file-earmark fs-1 text-muted"></i>@endif
                    </div>
                    <div class="d-flex align-items-start justify-content-between gap-2"><div><div class="fw-bold text-break">{{ $media['attributeCode'] ?? $media['role'] ?? 'Media' }}</div><div class="small text-muted">{{ strtoupper($kind) }}{{ !empty($media['sku']) ? ' · '.$media['sku'] : '' }}</div></div>@if($url)<a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i></a>@endif</div>
                </div></div>
            @empty
                <div class="col-12 text-muted">Belum ada media PIM.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="card detail-card mt-4">
    <div class="card-body p-3 p-lg-4">
        <div class="section-title"><i class="bi bi-clock-history"></i>Status Sinkronisasi</div>
        <div class="row g-3">
            <div class="col-md-3"><div class="detail-field"><div class="detail-label">PIM Synced</div><div class="detail-value">{{ $product->pim_synced_at?->format('d M Y H:i:s') ?: '—' }}</div></div></div>
            <div class="col-md-3"><div class="detail-field"><div class="detail-label">CARE Synced</div><div class="detail-value">{{ $product->care_synced_at?->format('d M Y H:i:s') ?: '—' }}</div></div></div>
            <div class="col-md-3"><div class="detail-field"><div class="detail-label">PIM Source</div><div class="detail-value">{{ $product->pimRecord?->source ?: '—' }}</div></div></div>
            <div class="col-md-3"><div class="detail-field"><div class="detail-label">Schema Version</div><div class="detail-value">{{ $product->pim_version ?: '—' }}</div></div></div>
        </div>
        @if($product->pim_payload)
            <details class="mt-4"><summary class="fw-semibold text-primary" style="cursor:pointer">Lihat payload mentah PIM</summary><pre class="json-payload mt-3 mb-0">{{ json_encode($product->pim_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
        @endif
    </div>
</div>
@endsection
