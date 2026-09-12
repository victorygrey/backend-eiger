@php
    $selectedFeaturedId = (int) old('featured_product_id', $tablet->featured_product_id ?? 0);
    $featuredProduct = $products->firstWhere('id', $selectedFeaturedId);

    $oldRecIds = old('recommendation_ids', isset($tablet) ? $tablet->recommendations->pluck('id')->all() : []);
    $selectedRecIds = collect($oldRecIds)->map(fn ($id) => (int) $id)->values();

    $selectedRecommendations = $selectedRecIds
        ->map(fn ($id) => $products->firstWhere('id', $id))
        ->filter();
@endphp

<div class="row g-4">
    {{-- LEFT COLUMN: Identity & Product Configurations --}}
    <div class="col-12 col-xl-8">
        {{-- SECTION 1: Device Identity --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-primary me-2"></i>Identitas Perangkat Display</h6>
                <span class="badge bg-light text-dark border">Interactive Table / Tablet</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Nama Tablet / Display <span class="text-danger">*</span></label>
                        <input type="text" id="tablet-name" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $tablet->name ?? '') }}" placeholder="Contoh: Tablet Meja Ekspedisi 01" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Slug URL / Device Identifier <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted small">/tablet/</span>
                            <input type="text" id="tablet-slug" name="slug" class="form-control font-monospace @error('slug') is-invalid @enderror"
                                   value="{{ old('slug', $tablet->slug ?? '') }}" placeholder="meja-01" required>
                        </div>
                        @error('slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Lokasi Penempatan Meja / Display</label>
                        <input type="text" name="location" class="form-control"
                               value="{{ old('location', $tablet->location ?? '') }}" placeholder="Contoh: Lantai 1 - Area Apparel & Hiking">
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 2: Featured Product Picker --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-star-fill text-warning me-2"></i>Produk Utama (Featured Product) <span class="text-danger">*</span></h6>
                    <small class="text-muted">Produk yang otomatis tampil pada layar standby tablet saat tidak ada interaksi.</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#featuredProductModal">
                    <i class="bi bi-search me-1"></i>Pilih Produk Utama
                </button>
            </div>
            <div class="card-body">
                <input type="hidden" name="featured_product_id" id="featured_product_id" value="{{ $selectedFeaturedId ?: '' }}" required>

                <div id="featured-product-card" class="{{ $featuredProduct ? '' : 'd-none' }}">
                    <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <img id="featured-img" src="{{ $featuredProduct?->image ?: 'https://placehold.co/80x80?text=No+Image' }}"
                                 alt="Featured" class="rounded border object-fit-cover shadow-sm bg-white" style="width: 72px; height: 72px;">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h6 class="mb-0 fw-bold text-dark" id="featured-title">{{ $featuredProduct?->name }}</h6>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="featured-zone">
                                        {{ $featuredProduct?->zone?->name ?? 'Zone EIGER' }}
                                    </span>
                                </div>
                                <div class="small text-muted font-monospace mb-1">
                                    SKU: <strong id="featured-sku">{{ $featuredProduct?->sku }}</strong>
                                </div>
                                <div class="small fw-semibold text-dark" id="featured-price">
                                    Rp {{ number_format($featuredProduct?->price ?? 0, 0, ',', '.') }}
                                    <span class="text-muted fw-normal ms-2">| Stok: <span id="featured-stock">{{ $featuredProduct?->stock ?? 0 }}</span></span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#featuredProductModal">
                            <i class="bi bi-arrow-repeat me-1"></i>Ganti Produk
                        </button>
                    </div>
                </div>

                <div id="featured-empty-card" class="text-center py-4 border border-dashed rounded-3 bg-light {{ $featuredProduct ? 'd-none' : '' }}">
                    <i class="bi bi-star fs-2 text-secondary d-block mb-2"></i>
                    <p class="text-muted small mb-2">Belum ada produk utama yang dipilih untuk display ini.</p>
                    <button type="button" class="btn btn-sm btn-eiger" data-bs-toggle="modal" data-bs-target="#featuredProductModal">
                        <i class="bi bi-plus-lg me-1"></i>Pilih Produk Sekarang
                    </button>
                </div>
                @error('featured_product_id')<div class="text-danger small mt-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- SECTION 3: Recommendations Dual-List Visual Picker --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-grid-fill text-primary me-2"></i>Produk Rekomendasi (Similar Products) <span class="text-danger">*</span></h6>
                    <small class="text-muted">Pilih 1 sampai 30 produk serupa. Urutan di sebelah kanan menentukan urutan di layar tablet.</small>
                </div>
                <span class="badge bg-secondary" id="rec-count-badge">{{ $selectedRecommendations->count() }} Terpilih</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    {{-- LEFT PANEL: Available Catalog Products --}}
                    <div class="col-12 col-md-6 border-end pe-md-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold small text-muted text-uppercase">Katalog Produk Tersedia</span>
                            <span class="small text-muted" id="avail-count">{{ $products->count() }} produk</span>
                        </div>

                        {{-- Search & Filter --}}
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="search-avail-products" class="form-control" placeholder="Cari nama / SKU produk...">
                        </div>

                        {{-- Scrollable List of Available Products --}}
                        <div class="overflow-auto border rounded-3 p-2 bg-light" style="max-height: 440px;" id="available-products-list">
                            @foreach($products as $product)
                                @php
                                    $isSelRec = $selectedRecIds->contains($product->id);
                                    $isFeat = $selectedFeaturedId === $product->id;
                                @endphp
                                <div class="product-item-row p-2 mb-2 bg-white rounded border d-flex align-items-center justify-content-between gap-2 transition-all {{ ($isSelRec || $isFeat) ? 'opacity-50' : '' }}"
                                     id="avail-prod-{{ $product->id }}"
                                     data-id="{{ $product->id }}"
                                     data-name="{{ strtolower($product->name) }}"
                                     data-sku="{{ strtolower($product->sku) }}"
                                     data-zone="{{ strtolower($product->zone?->name ?? '') }}"
                                     data-img="{{ $product->image ?: 'https://placehold.co/60x60?text=EIGER' }}"
                                     data-price="Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}"
                                     data-stock="{{ $product->stock ?? 0 }}">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <img src="{{ $product->image ?: 'https://placehold.co/60x60?text=EIGER' }}"
                                             alt="{{ $product->name }}" class="rounded border object-fit-cover flex-shrink-0" style="width: 44px; height: 44px;">
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold small text-truncate text-dark" title="{{ $product->name }}">{{ $product->name }}</div>
                                            <div class="text-muted small font-monospace">{{ $product->sku }}</div>
                                            <div class="text-secondary small">Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0 btn-add-rec"
                                            onclick="addRecommendation({{ $product->id }})"
                                            {{ ($isSelRec || $isFeat) ? 'disabled' : '' }}>
                                        <i class="bi bi-plus-lg"></i> Tambah
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- RIGHT PANEL: Selected Recommendations (Ordered) --}}
                    <div class="col-12 col-md-6 ps-md-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold small text-muted text-uppercase">Urutan Rekomendasi Terpilih</span>
                            <small class="text-muted">Gunakan tombol panah untuk mengatur urutan</small>
                        </div>

                        {{-- Hidden inputs container --}}
                        <div id="recommendation-inputs-container">
                            @foreach($selectedRecommendations as $product)
                                <input type="hidden" name="recommendation_ids[]" value="{{ $product->id }}" id="rec-input-{{ $product->id }}">
                            @endforeach
                        </div>

                        {{-- Selected List Container --}}
                        <div class="overflow-auto border rounded-3 p-2 bg-light" style="max-height: 480px;" id="selected-recommendations-list">
                            {{-- Empty State --}}
                            <div id="rec-empty-state" class="text-center py-5 text-muted {{ $selectedRecommendations->isNotEmpty() ? 'd-none' : '' }}">
                                <i class="bi bi-hand-index-thumb fs-2 text-secondary d-block mb-2"></i>
                                <div class="fw-semibold small">Belum ada rekomendasi dipilih</div>
                                <small>Klik tombol <strong>+ Tambah</strong> di panel kiri untuk memilih produk.</small>
                            </div>

                            {{-- Selected Cards --}}
                            @foreach($selectedRecommendations as $index => $product)
                                <div class="selected-rec-item p-2 mb-2 bg-white rounded border shadow-sm d-flex align-items-center justify-content-between gap-2"
                                     id="selected-card-{{ $product->id }}" data-id="{{ $product->id }}">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <span class="badge bg-secondary font-monospace rec-rank-badge" style="width: 28px;">#{{ $index + 1 }}</span>
                                        <img src="{{ $product->image ?: 'https://placehold.co/60x60?text=EIGER' }}"
                                             alt="{{ $product->name }}" class="rounded border object-fit-cover flex-shrink-0" style="width: 44px; height: 44px;">
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold small text-truncate text-dark" title="{{ $product->name }}">{{ $product->name }}</div>
                                            <div class="text-muted small font-monospace">{{ $product->sku }} | Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" onclick="moveItemUp({{ $product->id }})" title="Geser Naik">
                                            <i class="bi bi-arrow-up"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" onclick="moveItemDown({{ $product->id }})" title="Geser Turun">
                                            <i class="bi bi-arrow-down"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="removeRecommendation({{ $product->id }})" title="Hapus">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @error('recommendation_ids')<div class="text-danger small mt-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>@enderror
                        @error('recommendation_ids.*')<div class="text-danger small mt-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT COLUMN: Device Activation & System Status --}}
    <div class="col-12 col-xl-4">
        {{-- Card Activation --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock text-primary me-2"></i>Aktivasi & Keamanan</h6>
            </div>
            <div class="card-body">
                <label class="form-label fw-semibold">
                    {{ isset($tablet) ? 'Kode Aktivasi Baru (Opsional)' : 'Kode Aktivasi' }}
                    @if(!isset($tablet)) <span class="text-danger">*</span> @endif
                </label>
                <div class="input-group mb-2">
                    <input type="text" id="activation_code_input" name="activation_code" class="form-control font-monospace @error('activation_code') is-invalid @enderror"
                           autocomplete="new-password" placeholder="Contoh: LOBBY-01" {{ isset($tablet) ? '' : 'required' }}>
                    <button type="button" class="btn btn-outline-secondary" onclick="generateActivationCode()" title="Generate Random Code">
                        <i class="bi bi-dice-5"></i>
                    </button>
                </div>
                @error('activation_code')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <small class="text-muted d-block mb-3">
                    Kode ini diinputkan pada aplikasi display tablet pertama kali saat melakukan pairing.
                </small>

                <hr class="my-3">

                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is-active" name="is_active" value="1"
                           @checked(old('is_active', $tablet->is_active ?? true))>
                    <label class="form-check-label fw-semibold" for="is-active">Status Perangkat Aktif</label>
                </div>
                <small class="text-muted">Jika dinonaktifkan, tablet tidak akan dapat menampilkan katalog produk.</small>

                @isset($tablet)
                    <hr class="my-3">
                    <h6 class="fw-bold small text-muted text-uppercase mb-2">Status Operasional</h6>
                    <dl class="row small mb-0 g-1">
                        <dt class="col-6 text-muted">Config Version:</dt>
                        <dd class="col-6 font-monospace fw-bold">v{{ $tablet->config_version }}</dd>

                        <dt class="col-6 text-muted">Konektivitas:</dt>
                        <dd class="col-6">
                            @php($online = $tablet->last_seen_at?->gt(now()->subMinutes(2)) ?? false)
                            @if($online)
                                <span class="badge badge-success-soft"><i class="bi bi-wifi"></i> Online</span>
                            @else
                                <span class="badge badge-warning-soft"><i class="bi bi-wifi-off"></i> Offline</span>
                            @endif
                        </dd>

                        <dt class="col-6 text-muted">Terakhir Online:</dt>
                        <dd class="col-6">{{ $tablet->last_seen_at?->diffForHumans() ?? 'Belum terhubung' }}</dd>

                        <dt class="col-6 text-muted">API Endpoint:</dt>
                        <dd class="col-6 font-monospace small">/api/tablets/{{ $tablet->slug }}/display</dd>
                    </dl>
                @endisset
            </div>
        </div>

        {{-- Help Card --}}
        <div class="card border-0 shadow-sm rounded-3 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-2"></i>Panduan Interactive Table</h6>
                <p class="small text-muted mb-2">
                    Sistem <strong>Interactive Table</strong> menampilkan detail spesifikasi produk saat customer meletakkan produk di meja atau menyentuh layar.
                </p>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Produk Utama:</strong> Muncul saat standby atau RFID pertama terdeteksi.</li>
                    <li><strong>Rekomendasi:</strong> Muncul di baris bawah layar sebagai pilihan produk komparasi.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
    <a href="{{ route('admin.tablets.index') }}" class="btn btn-light px-4">Batal</a>
    <button type="submit" class="btn btn-eiger fw-bold px-4 shadow-sm">
        <i class="bi bi-cloud-upload me-1"></i>{{ isset($tablet) ? 'Publikasikan Konfigurasi (v'.($tablet->config_version + 1).')' : 'Simpan & Pasangkan Tablet' }}
    </button>
</div>

{{-- MODAL: Search & Select Featured Product --}}
<div class="modal fade" id="featuredProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-star-fill text-warning me-2"></i>Pilih Produk Utama (Featured Product)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="search-modal-featured" class="form-control" placeholder="Ketik nama atau SKU produk untuk memfilter...">
                </div>

                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 60px;">Foto</th>
                                <th>Nama & SKU</th>
                                <th>Zone / Kategori</th>
                                <th>Harga & Stok</th>
                                <th class="text-end" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="featured-modal-tbody">
                            @foreach($products as $prod)
                            <tr class="modal-prod-row"
                                data-id="{{ $prod->id }}"
                                data-name="{{ strtolower($prod->name) }}"
                                data-sku="{{ strtolower($prod->sku) }}"
                                data-img="{{ $prod->image ?: 'https://placehold.co/60x60?text=EIGER' }}"
                                data-rawname="{{ $prod->name }}"
                                data-rawsku="{{ $prod->sku }}"
                                data-zone="{{ $prod->zone?->name ?? 'Zone EIGER' }}"
                                data-price="Rp {{ number_format($prod->price ?? 0, 0, ',', '.') }}"
                                data-stock="{{ $prod->stock ?? 0 }}">
                                <td>
                                    <img src="{{ $prod->image ?: 'https://placehold.co/60x60?text=EIGER' }}"
                                         alt="{{ $prod->name }}" class="rounded border object-fit-cover shadow-sm" style="width: 44px; height: 44px;">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $prod->name }}</div>
                                    <div class="small text-muted font-monospace">{{ $prod->sku }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $prod->zone?->name ?? 'Zone EIGER' }}</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">Rp {{ number_format($prod->price ?? 0, 0, ',', '.') }}</div>
                                    <div class="small text-muted">Stok: {{ $prod->stock ?? 0 }}</div>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="selectFeaturedProduct({{ $prod->id }})">
                                        <i class="bi bi-check2"></i> Pilih
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // --- Auto slug generator from name ---
    const nameInput = document.getElementById('tablet-name');
    const slugInput = document.getElementById('tablet-slug');
    if (nameInput && slugInput && !slugInput.value) {
        nameInput.addEventListener('input', () => {
            slugInput.value = nameInput.value.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-');
        });
    }

    // --- Generate Activation Code ---
    function generateActivationCode() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let code = 'TB-';
        for (let i = 0; i < 6; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('activation_code_input').value = code;
    }

    // --- Filter Featured Modal ---
    document.getElementById('search-modal-featured').addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#featured-modal-tbody .modal-prod-row').forEach(row => {
            const name = row.dataset.name;
            const sku = row.dataset.sku;
            row.style.display = (name.includes(q) || sku.includes(q)) ? '' : 'none';
        });
    });

    // --- Filter Available Recommendations List ---
    document.getElementById('search-avail-products').addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        let count = 0;
        document.querySelectorAll('#available-products-list .product-item-row').forEach(row => {
            const name = row.dataset.name;
            const sku = row.dataset.sku;
            const zone = row.dataset.zone;
            const match = (name.includes(q) || sku.includes(q) || zone.includes(q));
            row.style.display = match ? '' : 'none';
            if (match) count++;
        });
        document.getElementById('avail-count').textContent = `${count} produk`;
    });

    // --- Select Featured Product ---
    function selectFeaturedProduct(productId) {
        const row = document.querySelector(`#featured-modal-tbody tr[data-id="${productId}"]`);
        if (!row) return;

        document.getElementById('featured_product_id').value = productId;
        document.getElementById('featured-img').src = row.dataset.img;
        document.getElementById('featured-title').textContent = row.dataset.rawname;
        document.getElementById('featured-sku').textContent = row.dataset.rawsku;
        document.getElementById('featured-zone').textContent = row.dataset.zone;
        document.getElementById('featured-price').innerHTML = `${row.dataset.price} <span class="text-muted fw-normal ms-2">| Stok: ${row.dataset.stock}</span>`;

        document.getElementById('featured-empty-card').classList.add('d-none');
        document.getElementById('featured-product-card').classList.remove('d-none');

        // If this product was in recommendations, remove it automatically to prevent validation error
        removeRecommendation(productId);

        // Update available product disabled state
        updateAvailableItemsState();

        // Close modal
        const modalEl = document.getElementById('featuredProductModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }

    // --- Add to Recommendations ---
    function addRecommendation(productId) {
        const currentInputs = document.querySelectorAll('#recommendation-inputs-container input');
        if (currentInputs.length >= 30) {
            alert('Maksimal 30 produk rekomendasi yang dapat ditambahkan.');
            return;
        }

        const featuredId = parseInt(document.getElementById('featured_product_id').value || 0);
        if (productId === featuredId) {
            alert('Produk ini sudah dipilih sebagai Produk Utama (Featured).');
            return;
        }

        if (document.getElementById(`rec-input-${productId}`)) return;

        const row = document.getElementById(`avail-prod-${productId}`);
        if (!row) return;

        const name = row.querySelector('.fw-semibold').textContent;
        const sku = row.dataset.sku.toUpperCase();
        const price = row.dataset.price;
        const img = row.dataset.img;

        // 1. Add hidden input
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'recommendation_ids[]';
        input.value = productId;
        input.id = `rec-input-${productId}`;
        document.getElementById('recommendation-inputs-container').appendChild(input);

        // 2. Add visual card
        const card = document.createElement('div');
        card.className = 'selected-rec-item p-2 mb-2 bg-white rounded border shadow-sm d-flex align-items-center justify-content-between gap-2';
        card.id = `selected-card-${productId}`;
        card.dataset.id = productId;
        card.innerHTML = `
            <div class="d-flex align-items-center gap-2 overflow-hidden">
                <span class="badge bg-secondary font-monospace rec-rank-badge" style="width: 28px;">#</span>
                <img src="${img}" alt="${name}" class="rounded border object-fit-cover flex-shrink-0" style="width: 44px; height: 44px;">
                <div class="overflow-hidden">
                    <div class="fw-semibold small text-truncate text-dark" title="${name}">${name}</div>
                    <div class="text-muted small font-monospace">${sku} | ${price}</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" onclick="moveItemUp(${productId})" title="Geser Naik">
                    <i class="bi bi-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" onclick="moveItemDown(${productId})" title="Geser Turun">
                    <i class="bi bi-arrow-down"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="removeRecommendation(${productId})" title="Hapus">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `;

        document.getElementById('selected-recommendations-list').appendChild(card);
        updateRanks();
        updateAvailableItemsState();
    }

    // --- Remove Recommendation ---
    function removeRecommendation(productId) {
        const input = document.getElementById(`rec-input-${productId}`);
        if (input) input.remove();

        const card = document.getElementById(`selected-card-${productId}`);
        if (card) card.remove();

        updateRanks();
        updateAvailableItemsState();
    }

    // --- Move Item Up ---
    function moveItemUp(productId) {
        const card = document.getElementById(`selected-card-${productId}`);
        const input = document.getElementById(`rec-input-${productId}`);
        if (!card || !card.previousElementSibling || card.previousElementSibling.id === 'rec-empty-state') return;

        const prevCard = card.previousElementSibling;
        const prevInput = input.previousElementSibling;

        card.parentNode.insertBefore(card, prevCard);
        input.parentNode.insertBefore(input, prevInput);
        updateRanks();
    }

    // --- Move Item Down ---
    function moveItemDown(productId) {
        const card = document.getElementById(`selected-card-${productId}`);
        const input = document.getElementById(`rec-input-${productId}`);
        if (!card || !card.nextElementSibling) return;

        const nextCard = card.nextElementSibling;
        const nextInput = input.nextElementSibling;

        card.parentNode.insertBefore(nextCard, card);
        input.parentNode.insertBefore(nextInput, input);
        updateRanks();
    }

    // --- Update Rank Badges & Counts ---
    function updateRanks() {
        const cards = document.querySelectorAll('#selected-recommendations-list .selected-rec-item');
        cards.forEach((card, index) => {
            const badge = card.querySelector('.rec-rank-badge');
            if (badge) badge.textContent = `#${index + 1}`;
        });

        const emptyState = document.getElementById('rec-empty-state');
        if (emptyState) {
            emptyState.classList.toggle('d-none', cards.length > 0);
        }

        document.getElementById('rec-count-badge').textContent = `${cards.length} Terpilih`;
    }

    // --- Update Available Items Disabled State ---
    function updateAvailableItemsState() {
        const featuredId = parseInt(document.getElementById('featured_product_id').value || 0);
        const recIds = Array.from(document.querySelectorAll('#recommendation-inputs-container input'))
            .map(inp => parseInt(inp.value));

        document.querySelectorAll('#available-products-list .product-item-row').forEach(row => {
            const id = parseInt(row.dataset.id);
            const isRec = recIds.includes(id);
            const isFeat = (id === featuredId);
            const btn = row.querySelector('.btn-add-rec');

            if (isRec || isFeat) {
                row.classList.add('opacity-50');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = isFeat ? '<i class="bi bi-star-fill"></i> Utama' : '<i class="bi bi-check"></i> Ada';
                }
            } else {
                row.classList.remove('opacity-50');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-plus-lg"></i> Tambah';
                }
            }
        });
    }

    // Initial update on page load
    updateRanks();
    updateAvailableItemsState();
</script>
@endpush
