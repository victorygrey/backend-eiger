@extends('layouts.admin')

@section('title', 'Table Expedition')
@section('page-title', 'Table Expedition')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Konfigurasi</li>
    <li class="breadcrumb-item active">Table Expedition</li>
@endsection

@push('styles')
<style>
    .expedition-table { min-width: 1120px; }
    .expedition-table thead th { background:#171b2d; border:0; color:#ff6a00; font-size:.7rem; letter-spacing:.055em; padding:.95rem; text-transform:uppercase; white-space:nowrap; }
    .expedition-table tbody td { border-color:#edf0f3; padding:.9rem; vertical-align:middle; }
    .expedition-table tbody tr:hover { background:rgba(255,106,0,.035); }
    .product-cover { background:#f4f5f7; border:1px solid #e3e6ea; border-radius:10px; height:52px; object-fit:cover; width:52px; }
    .product-cover-empty { align-items:center; color:#9aa1ac; display:flex; justify-content:center; }
    .readiness-track { background:#e8ebef; border-radius:999px; height:6px; min-width:110px; overflow:hidden; }
    .readiness-track > span { display:block; height:100%; transition:width .2s ease; }
    .source-flow { background:linear-gradient(135deg, rgba(255,106,0,.1), rgba(23,27,45,.035)); border:1px solid rgba(255,106,0,.2); }
    .standby-preview { background:#111827; border-radius:12px; min-height:180px; overflow:hidden; }
    .standby-preview img,.standby-preview video { height:220px; object-fit:cover; width:100%; }
    .comparison-list { max-height:360px; min-height:260px; overflow:auto; }
    .comparison-product-thumb { background:#f4f5f7; border:1px solid #e3e6ea; border-radius:8px; height:42px; object-fit:cover; width:42px; }
</style>
@endpush

@section('content')
@php
    $standbyMediaUrl = \App\Support\PimMediaUrl::toPublicUrl($standby['media_url']);
    $availableComparisonIds = collect($comparisonProducts)->pluck('id')->map(fn ($id) => (int) $id);
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-compass-fill text-warning me-2"></i>Table Expedition</h4>
        <p class="text-muted small mb-0">Monitoring produk RFID yang tersedia pada meja interaktif dan konfigurasi tampilan standby global.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#standbyConfigModal">
            <i class="bi bi-display me-1"></i>Konfigurasi Standby
        </button>
        <a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-eiger fw-bold">
            <i class="bi bi-broadcast-pin me-1"></i>Atur RFID Table Expedition
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3 mb-4">
    @foreach([
        ['label' => 'RFID Aktif', 'value' => $summary['active'], 'icon' => 'bi-broadcast-pin', 'color' => '#2563eb'],
        ['label' => 'Produk Siap', 'value' => $summary['ready'], 'icon' => 'bi-check-circle-fill', 'color' => '#16a34a'],
        ['label' => 'Data Belum Lengkap', 'value' => $summary['incomplete'], 'icon' => 'bi-exclamation-triangle-fill', 'color' => '#f59e0b'],
        ['label' => 'Pernah Dipindai', 'value' => $summary['scanned'], 'icon' => 'bi-clock-history', 'color' => '#64748b'],
    ] as $card)
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3 py-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:42px;height:42px;background:{{ $card['color'] }}"><i class="bi {{ $card['icon'] }} fs-5"></i></span>
                <div><div class="small text-muted">{{ $card['label'] }}</div><div class="fs-4 fw-bold lh-1">{{ $card['value'] }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="source-flow rounded-3 p-3 mb-4 small">
    <div class="fw-bold mb-1"><i class="bi bi-diagram-3-fill text-warning me-1"></i>Sumber data tunggal</div>
    <div class="text-muted">Aktifkan RFID untuk Table Expedition melalui menu RFID Tags. Detail produk berasal dari PIM, harga dan stok dari CARE, sedangkan media dilayani dari storage lokal CMS.</div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white px-3 px-lg-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h6 class="fw-bold mb-1">Produk RFID Aktif</h6>
            <small class="text-muted">Daftar ini terbentuk otomatis dari status Table Expedition pada Master RFID.</small>
        </div>
        <span class="badge rounded-pill text-bg-dark">{{ $items->count() }} produk</span>
    </div>
    <div class="table-responsive">
        <table class="table expedition-table align-middle mb-0">
            <thead><tr>
                <th class="ps-4">RFID</th>
                <th>Produk & SKU</th>
                <th>Kategori / Activity</th>
                <th>Kelengkapan Data</th>
                <th>Media</th>
                <th>Terakhir Dipindai</th>
                <th class="pe-4 text-end">Action</th>
            </tr></thead>
            <tbody>
                @if($items->isNotEmpty())
                @foreach($items as $item)
                    @php
                        $product = $item->product;
                        $readiness = $item->readiness;
                        $imageUrl = \App\Support\PimMediaUrl::toPublicUrl($product?->image);
                        $activities = collect($product?->activities ?? [])->pluck('name')->filter()->take(3);
                        $category = $product?->atomSubCategory?->name ?? $product?->atomCategory?->name ?? $product?->category;
                        $selectedComparisonIds = collect($item->similar_product_ids ?? [])
                            ->map(fn ($id) => (int) $id)
                            ->filter(fn ($id) => $id !== (int) $product?->id && $availableComparisonIds->contains($id))
                            ->unique()
                            ->take(5)
                            ->values()
                            ->all();
                    @endphp
                    <tr>
                        <td class="ps-4">
                            @if($item->rfidTag?->name)<div class="small fw-semibold text-primary mb-1"><i class="bi bi-tag-fill me-1"></i>{{ $item->rfidTag->name }}</div>@endif
                            <code class="fw-bold text-dark">{{ $item->rfid_tag }}</code>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $product?->name }}" class="product-cover flex-shrink-0">
                                @else
                                    <span class="product-cover product-cover-empty flex-shrink-0"><i class="bi bi-image fs-5"></i></span>
                                @endif
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-dark text-truncate" style="max-width:250px" title="{{ $product?->name }}">{{ $product?->name ?? 'Produk tidak terhubung' }}</div>
                                    <div class="small text-muted font-monospace">{{ $product?->sku ?? '-' }}</div>
                                    @if($product)<div class="small text-secondary">Rp {{ number_format((float) $product->price, 0, ',', '.') }} · Stok {{ $product->stock }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold small">{{ $category ?: 'Kategori belum tersedia' }}</div>
                            <div class="mt-1 d-flex flex-wrap gap-1">
                                @if($activities->isNotEmpty())
                                    @foreach($activities as $activity)
                                        <span class="badge bg-light text-dark border">{{ $activity }}</span>
                                    @endforeach
                                @else
                                    <span class="small text-muted">Activity belum tersedia</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="readiness-track"><span style="width:{{ $readiness['score'] }}%;background:{{ $readiness['ready'] ? '#16a34a' : '#f59e0b' }}"></span></div>
                                <strong class="small">{{ $readiness['score'] }}%</strong>
                            </div>
                            @if($readiness['ready'])
                                <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle-fill me-1"></i>Siap</span>
                            @else
                                <span class="badge rounded-pill text-bg-warning" title="{{ implode(', ', $readiness['missing']) }}"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ count($readiness['missing']) }} belum lengkap</span>
                            @endif
                        </td>
                        <td>
                            <div class="small"><i class="bi {{ $imageUrl ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' }} me-1"></i>Foto</div>
                            <div class="small"><i class="bi {{ $readiness['video_url'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' }} me-1"></i>Video</div>
                        </td>
                        <td>
                            @if($item->last_scanned_at)
                                <div class="small fw-semibold">{{ $item->last_scanned_at->diffForHumans() }}</div>
                                <div class="small text-muted">{{ $item->last_scanned_at->format('d M Y H:i') }}</div>
                            @else
                                <span class="small text-muted">Belum pernah</span>
                            @endif
                        </td>
                        <td class="pe-4 text-end">
                            @if($product)
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-success comparison-config-button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#comparisonConfigModal"
                                            data-action="{{ route('admin.table-expedition.comparisons.update', $item) }}"
                                            data-item-id="{{ $item->id }}"
                                            data-product-id="{{ $product->id }}"
                                            data-product-name="{{ $product->name }}"
                                            data-selected='@json($selectedComparisonIds)'>
                                        <i class="bi bi-shuffle me-1"></i>Komparasi
                                        <span class="badge text-bg-success ms-1">{{ count($selectedComparisonIds) }}/5</span>
                                    </button>
                                    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye-fill me-1"></i>Detail</a>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @else
                    <tr><td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-broadcast-pin fs-2 d-block mb-2"></i>
                        <div class="fw-semibold">Belum ada RFID aktif untuk Table Expedition</div>
                        <small>Aktifkan status Table Expedition melalui menu RFID Tags.</small>
                    </td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="standbyConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form action="{{ route('admin.table-expedition.config') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-display text-warning me-2"></i>Konfigurasi Standby Global</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="mb-3"><label class="form-label fw-semibold">Judul Standby</label><input type="text" name="standby_title" class="form-control @error('standby_title') is-invalid @enderror" value="{{ old('standby_title', $standby['title']) }}" required>@error('standby_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="mb-3"><label class="form-label fw-semibold">Subjudul</label><input type="text" name="standby_subtitle" class="form-control @error('standby_subtitle') is-invalid @enderror" value="{{ old('standby_subtitle', $standby['subtitle']) }}">@error('standby_subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="mb-3"><label class="form-label fw-semibold">Panduan Penggunaan</label><textarea name="usage_instructions" rows="5" class="form-control @error('usage_instructions') is-invalid @enderror" placeholder="Satu langkah per baris">{{ old('usage_instructions', implode("\n", $standby['instructions'])) }}</textarea>@error('usage_instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Masukkan satu langkah pada setiap baris.</div></div>
                            <div><label class="form-label fw-semibold">Gambar atau Video Standby</label><input type="file" name="standby_media_file" class="form-control @error('standby_media_file') is-invalid @enderror" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">@error('standby_media_file')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">JPG, PNG, WebP, MP4, atau WebM. File disimpan ke storage lokal CMS.</div></div>
                            @if($standbyMediaUrl)<div class="form-check mt-3"><input type="hidden" name="remove_standby_media" value="0"><input class="form-check-input" type="checkbox" name="remove_standby_media" value="1" id="remove-standby-media"><label class="form-check-label" for="remove-standby-media">Lepaskan media standby saat ini</label></div>@endif
                        </div>
                        <div class="col-lg-5">
                            <label class="form-label fw-semibold">Preview Media</label>
                            <div class="standby-preview d-flex align-items-center justify-content-center">
                                @if($standbyMediaUrl && $standby['media_type'] === 'video')
                                    <video controls preload="metadata"><source src="{{ $standbyMediaUrl }}"></video>
                                @elseif($standbyMediaUrl)
                                    <img src="{{ $standbyMediaUrl }}" alt="Media standby Table Expedition">
                                @else
                                    <div class="text-center text-white-50 p-4"><i class="bi bi-display fs-1 d-block mb-2"></i>Belum ada media standby</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-eiger fw-bold"><i class="bi bi-check-lg me-1"></i>Simpan Konfigurasi</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form id="comparisonConfigForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="form_context" value="comparisons">
                <input type="hidden" name="comparison_item_id" id="comparisonItemId">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold"><i class="bi bi-shuffle text-success me-2"></i>Atur Produk Komparasi</h5>
                        <div class="small text-muted" id="comparisonPrimaryName"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        Pilih maksimal lima produk yang boleh dibandingkan dengan produk utama. Hanya produk dengan RFID Table Expedition aktif yang tersedia.
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="comparisonSearch" class="fw-semibold">Produk tersedia</label>
                                <span class="small text-muted" id="comparisonAvailableCount"></span>
                            </div>
                            <input type="search" id="comparisonSearch" class="form-control form-control-sm mb-2" placeholder="Cari nama atau SKU produk...">
                            <div id="comparisonAvailableList" class="comparison-list border rounded-3 p-2 bg-light"></div>
                        </div>
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Urutan komparasi terpilih</span>
                                <span class="badge text-bg-secondary" id="comparisonSelectedCount">0 / 5</span>
                            </div>
                            <div id="comparisonSelectedInputs"></div>
                            <div id="comparisonSelectedList" class="comparison-list border rounded-3 p-2 bg-light"></div>
                            <div id="comparisonLimitMessage" class="small text-danger mt-2 d-none">Maksimal lima produk komparasi dapat dipilih.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-eiger fw-bold"><i class="bi bi-check-lg me-1"></i>Simpan Pilihan Komparasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const products = @json($comparisonProducts);
    const productById = new Map(products.map(product => [Number(product.id), product]));
    const modal = document.getElementById('comparisonConfigModal');
    const form = document.getElementById('comparisonConfigForm');
    const search = document.getElementById('comparisonSearch');
    const availableList = document.getElementById('comparisonAvailableList');
    const selectedList = document.getElementById('comparisonSelectedList');
    const selectedInputs = document.getElementById('comparisonSelectedInputs');
    const selectedCount = document.getElementById('comparisonSelectedCount');
    const availableCount = document.getElementById('comparisonAvailableCount');
    const limitMessage = document.getElementById('comparisonLimitMessage');
    let primaryId = 0;
    let selectedIds = [];

    function productRow(product, actionLabel, actionClass, action) {
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-2 bg-white border rounded-3 p-2 mb-2';

        const image = document.createElement(product.image ? 'img' : 'span');
        image.className = 'comparison-product-thumb flex-shrink-0';
        if (product.image) {
            image.src = product.image;
            image.alt = product.name;
        } else {
            image.classList.add('d-inline-flex', 'align-items-center', 'justify-content-center', 'text-muted');
            image.innerHTML = '<i class="bi bi-image"></i>';
        }

        const info = document.createElement('div');
        info.className = 'overflow-hidden flex-grow-1';
        const name = document.createElement('div');
        name.className = 'fw-semibold small text-truncate';
        name.textContent = product.name;
        const sku = document.createElement('div');
        sku.className = 'small text-muted font-monospace';
        sku.textContent = product.sku || '-';
        info.append(name, sku);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = `btn btn-sm ${actionClass}`;
        button.textContent = actionLabel;
        button.addEventListener('click', action);
        row.append(image, info, button);

        return row;
    }

    function render() {
        const query = search.value.trim().toLowerCase();
        const available = products.filter(product => Number(product.id) !== primaryId
            && !selectedIds.includes(Number(product.id))
            && (!query || `${product.name} ${product.sku || ''}`.toLowerCase().includes(query)));

        availableList.innerHTML = '';
        available.forEach(product => {
            availableList.append(productRow(product, '+ Tambah', 'btn-outline-primary', () => {
                if (selectedIds.length >= 5) {
                    limitMessage.classList.remove('d-none');
                    return;
                }
                selectedIds.push(Number(product.id));
                limitMessage.classList.add('d-none');
                render();
            }));
        });
        if (!available.length) {
            availableList.innerHTML = '<div class="text-center text-muted small py-5">Tidak ada produk yang sesuai.</div>';
        }
        availableCount.textContent = `${available.length} produk`;

        selectedList.innerHTML = '';
        selectedInputs.innerHTML = '';
        selectedIds.forEach((id, index) => {
            const product = productById.get(id);
            if (!product) return;

            const row = productRow(product, 'Hapus', 'btn-outline-danger', () => {
                selectedIds = selectedIds.filter(selectedId => selectedId !== id);
                render();
            });
            const rank = document.createElement('span');
            rank.className = 'badge text-bg-dark flex-shrink-0';
            rank.textContent = `#${index + 1}`;
            row.prepend(rank);

            const controls = document.createElement('div');
            controls.className = 'btn-group btn-group-sm';
            [['↑', -1], ['↓', 1]].forEach(([label, direction]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-outline-secondary';
                button.textContent = label;
                button.disabled = direction === -1 ? index === 0 : index === selectedIds.length - 1;
                button.addEventListener('click', () => {
                    const target = index + direction;
                    [selectedIds[index], selectedIds[target]] = [selectedIds[target], selectedIds[index]];
                    render();
                });
                controls.append(button);
            });
            row.insertBefore(controls, row.lastElementChild);
            selectedList.append(row);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'similar_product_ids[]';
            input.value = String(id);
            selectedInputs.append(input);
        });
        if (!selectedIds.length) {
            selectedList.innerHTML = '<div class="text-center text-muted small py-5"><i class="bi bi-hand-index-thumb fs-3 d-block mb-2"></i>Belum ada produk komparasi dipilih.</div>';
        }
        selectedCount.textContent = `${selectedIds.length} / 5`;
    }

    modal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        if (!button) return;
        form.action = button.dataset.action;
        document.getElementById('comparisonItemId').value = button.dataset.itemId;
        primaryId = Number(button.dataset.productId);
        selectedIds = JSON.parse(button.dataset.selected || '[]')
            .map(Number)
            .filter(id => productById.has(id) && id !== primaryId)
            .slice(0, 5);
        document.getElementById('comparisonPrimaryName').textContent = `Produk utama: ${button.dataset.productName}`;
        search.value = '';
        limitMessage.classList.add('d-none');
        render();
    });
    search.addEventListener('input', render);

    @if($errors->any())
        if (@json(old('form_context')) === 'comparisons') {
            const failedTrigger = document.querySelector(`.comparison-config-button[data-item-id="${@json(old('comparison_item_id'))}"]`);
            if (failedTrigger) {
                bootstrap.Modal.getOrCreateInstance(modal).show(failedTrigger);
            }
        } else {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('standbyConfigModal')).show();
        }
    @endif
});
</script>
@endpush
