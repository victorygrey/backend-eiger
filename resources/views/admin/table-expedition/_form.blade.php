@php
    $currentTag = old('rfid_tag', $item->rfid_tag ?? '');
    $selectedProdId = (int) old('product_id', $item->product_id ?? 0);
    $selectedAct = old('activity_slug', $item->activity_slug ?? '');

    $oldSimilarIds = old('similar_product_ids', isset($item) ? ($item->similar_product_ids ?? []) : []);
    if (!is_array($oldSimilarIds)) {
        $oldSimilarIds = [];
    }
    $selectedSimilarIds = collect($oldSimilarIds)->map(fn ($id) => (int) $id)->values();
    $selectedSimilarProducts = $selectedSimilarIds
        ->map(fn ($id) => $products->firstWhere('id', $id))
        ->filter();
@endphp

<div class="row g-4">
    {{-- LEFT COLUMN: Primary Configuration & Product Knowledge --}}
    <div class="col-12 col-xl-8">
        {{-- Card 1: RFID Identification & Product --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-broadcast-pin text-primary me-2"></i>Identifikasi Tag RFID & Produk EIGER</h6>
                <span class="badge bg-light text-dark border">Table Expedition Trigger</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold mb-0">Tag RFID (EPC / UID) <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none toggle-rfid-manual-btn" style="font-size: 0.8rem;">
                                <i class="bi bi-pencil-square"></i> Input Manual
                            </button>
                        </div>
                        <div class="rfid-select-wrap">
                            <select name="rfid_tag" class="form-select font-monospace rfid-select-field @error('rfid_tag') is-invalid @enderror" required>
                                <option value="">-- Pilih dari Master RFID Tags ({{ $availableRfidTags->count() }} terdaftar) --</option>
                                @foreach($availableRfidTags as $rt)
                                    <option value="{{ $rt->uid }}" @selected($rt->uid === $currentTag)>
                                        {{ $rt->name ? $rt->name . ' — ' : '' }}{{ $rt->uid }} {{ $rt->product ? '(' . $rt->product->name . ')' : '' }}
                                    </option>
                                @endforeach
                                @if(isset($item) && !$availableRfidTags->contains('uid', $item->rfid_tag) && $item->rfid_tag)
                                    <option value="{{ $item->rfid_tag }}" selected>
                                        {{ $item->rfid_tag }} (Tag saat ini / Kustom)
                                    </option>
                                @endif
                            </select>
                            @error('rfid_tag')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text small">Pilih tag RFID dari master database atau gunakan tombol "Input Manual" untuk kode hex kustom.</div>
                        </div>
                        <div class="rfid-manual-wrap d-none mt-2">
                            <input type="text" class="form-control font-monospace rfid-manual-field" value="{{ old('rfid_tag', $item->rfid_tag ?? '') }}" placeholder="E280116060000204..." disabled>
                            <div class="form-text small">Ketik kode hex UID/EPC RFID secara langsung jika belum ada di master.</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Produk EIGER Terkait <span class="text-danger">*</span></label>
                        @include('admin.partials.product-mapping-picker')
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Aktivitas Outdoor Terkait</label>
                        <select name="activity_slug" class="form-select @error('activity_slug') is-invalid @enderror">
                            <option value="">-- Bebas (General / Mengikuti Kategori) --</option>
                            @foreach($activities as $act)
                                <option value="{{ $act->slug }}" @selected($act->slug === $selectedAct)>
                                    {{ $act->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('activity_slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Peruntukan (Ideal For)</label>
                        <input type="text" name="ideal_for" class="form-control @error('ideal_for') is-invalid @enderror"
                               value="{{ old('ideal_for', $item->ideal_for ?? '') }}"
                               placeholder="Contoh: Mountaineering, High Alpine, Ekspedisi Hujan Lebat">
                        @error('ideal_for')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Teks singkat yang menjelaskan kondisi alam atau skenario penggunaan terbaik produk ini.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Similar Products for Recommendation & Comparison (Dual-List Visual Picker) --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shuffle text-success me-2"></i>Produk Rekomendasi & Komparasi Serupa</h6>
                    <small class="text-muted">Pilih hingga maksimal 5 produk komparasi. Klik tombol <strong>+ Tambah</strong> pada katalog di sebelah kiri.</small>
                </div>
                <span class="badge bg-secondary" id="sim-count-badge">{{ $selectedSimilarProducts->count() }} / 5 Terpilih</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    {{-- LEFT PANEL: Available Catalog Products --}}
                    <div class="col-12 col-md-6 border-end pe-md-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold small text-muted text-uppercase">Katalog Produk Tersedia</span>
                            <span class="small text-muted" id="avail-sim-count">{{ $products->count() }} produk</span>
                        </div>

                        {{-- Search & Filter --}}
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="search-avail-sim-products" class="form-control" placeholder="Cari nama / SKU produk...">
                        </div>

                        {{-- Scrollable List of Available Products --}}
                        <div class="overflow-auto border rounded-3 p-2 bg-light" style="max-height: 420px;" id="available-sim-products-list">
                            @foreach($products as $prod)
                                @php
                                    $isSel = $selectedSimilarIds->contains($prod->id);
                                    $isMain = $selectedProdId === $prod->id;
                                @endphp
                                <div class="product-item-row p-2 mb-2 bg-white rounded border d-flex align-items-center justify-content-between gap-2 transition-all {{ ($isSel || $isMain) ? 'opacity-50' : '' }}"
                                     id="avail-sim-prod-{{ $prod->id }}"
                                     data-id="{{ $prod->id }}"
                                     data-name="{{ strtolower($prod->name) }}"
                                     data-sku="{{ strtolower($prod->sku) }}"
                                     data-zone="{{ strtolower($prod->zone?->name ?? '') }}"
                                     data-rawname="{{ $prod->name }}"
                                     data-rawsku="{{ $prod->sku }}"
                                     data-img="{{ $prod->image ?: 'https://placehold.co/60x60?text=EIGER' }}"
                                     data-price="Rp {{ number_format($prod->price ?? 0, 0, ',', '.') }}">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <img src="{{ $prod->image ?: 'https://placehold.co/60x60?text=EIGER' }}"
                                             alt="{{ $prod->name }}" class="rounded border object-fit-cover flex-shrink-0" style="width: 44px; height: 44px;">
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold small text-truncate text-dark" title="{{ $prod->name }}">{{ $prod->name }}</div>
                                            <div class="text-muted small font-monospace">{{ $prod->sku }}</div>
                                            <div class="text-secondary small">Rp {{ number_format($prod->price ?? 0, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0 btn-add-sim"
                                            onclick="addSimilarProduct({{ $prod->id }})"
                                            {{ ($isSel || $isMain) ? 'disabled' : '' }}>
                                        @if($isMain)
                                            <i class="bi bi-star-fill"></i> Utama
                                        @elseif($isSel)
                                            <i class="bi bi-check"></i> Ada
                                        @else
                                            <i class="bi bi-plus-lg"></i> Tambah
                                        @endif
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- RIGHT PANEL: Selected Similar Products --}}
                    <div class="col-12 col-md-6 ps-md-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold small text-muted text-uppercase">Daftar Komparasi Terpilih</span>
                            <small class="text-muted">Maksimal 5 produk komparasi</small>
                        </div>

                        {{-- Hidden inputs container --}}
                        <div id="similar-inputs-container">
                            @foreach($selectedSimilarProducts as $prod)
                                <input type="hidden" name="similar_product_ids[]" value="{{ $prod->id }}" id="sim-input-{{ $prod->id }}">
                            @endforeach
                        </div>

                        {{-- Selected List Container --}}
                        <div class="overflow-auto border rounded-3 p-2 bg-light" style="max-height: 420px;" id="selected-similar-list">
                            {{-- Empty State --}}
                            <div id="sim-empty-state" class="text-center py-5 text-muted {{ $selectedSimilarProducts->isNotEmpty() ? 'd-none' : '' }}">
                                <i class="bi bi-hand-index-thumb fs-2 text-secondary d-block mb-2"></i>
                                <div class="fw-semibold small">Belum ada produk komparasi dipilih</div>
                                <small>Klik tombol <strong>+ Tambah</strong> di katalog kiri untuk memilih produk (maksimal 5).</small>
                            </div>

                            {{-- Selected Cards --}}
                            @foreach($selectedSimilarProducts as $index => $prod)
                                <div class="selected-sim-item p-2 mb-2 bg-white rounded border shadow-sm d-flex align-items-center justify-content-between gap-2"
                                     id="selected-sim-card-{{ $prod->id }}" data-id="{{ $prod->id }}">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <span class="badge bg-secondary font-monospace sim-rank-badge" style="width: 28px;">#{{ $index + 1 }}</span>
                                        <img src="{{ $prod->image ?: 'https://placehold.co/60x60?text=EIGER' }}"
                                             alt="{{ $prod->name }}" class="rounded border object-fit-cover flex-shrink-0" style="width: 44px; height: 44px;">
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold small text-truncate text-dark" title="{{ $prod->name }}">{{ $prod->name }}</div>
                                            <div class="text-muted small font-monospace">{{ $prod->sku }} | Rp {{ number_format($prod->price ?? 0, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" onclick="moveSimUp({{ $prod->id }})" title="Geser Naik">
                                            <i class="bi bi-arrow-up"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" onclick="moveSimDown({{ $prod->id }})" title="Geser Turun">
                                            <i class="bi bi-arrow-down"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="removeSimilarProduct({{ $prod->id }})" title="Hapus">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @error('similar_product_ids')<div class="text-danger small mt-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>@enderror
                        @error('similar_product_ids.*')<div class="text-danger small mt-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT COLUMN: Status & Operational Info --}}
    <div class="col-12 col-xl-4">
        {{-- Card 1: Status & Notes --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold"><i class="bi bi-sliders text-primary me-2"></i>Status & Operasional</h6>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="item-is-active" name="is_active" value="1"
                           @checked(old('is_active', $item->is_active ?? true))>
                    <label class="form-check-label fw-semibold" for="item-is-active">Pemetaan RFID Aktif</label>
                </div>
                <small class="text-muted d-block mb-3">
                    Jika dinonaktifkan, scanner Table Expedition tidak akan merespons atau menampilkan data produk saat mendeteksi tag ini.
                </small>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Catatan Internal / Penempatan</label>
                    <input type="text" name="notes" class="form-control"
                           value="{{ old('notes', $item->notes ?? '') }}" placeholder="Contoh: Meja Ekspedisi Display Utama">
                </div>

                @isset($item)
                    <hr class="my-3">
                    <h6 class="fw-bold small text-muted text-uppercase mb-2">Informasi Sistem</h6>
                    <dl class="row small mb-0 g-1">
                        <dt class="col-6 text-muted">ID Database:</dt>
                        <dd class="col-6 font-monospace fw-bold">#{{ $item->id }}</dd>

                        <dt class="col-6 text-muted">Terakhir Di-scan:</dt>
                        <dd class="col-6">{{ $item->last_scanned_at ? $item->last_scanned_at->diffForHumans() : 'Belum pernah' }}</dd>

                        <dt class="col-6 text-muted">Waktu Dibuat:</dt>
                        <dd class="col-6 text-muted">{{ $item->created_at->format('d M Y H:i') }}</dd>

                        <dt class="col-6 text-muted">Pembaruan Terakhir:</dt>
                        <dd class="col-6 text-muted">{{ $item->updated_at->format('d M Y H:i') }}</dd>
                    </dl>
                @endisset
            </div>
        </div>

        {{-- Card 2: Help Guide --}}
        <div class="card border-0 shadow-sm rounded-3 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-2"></i>Panduan Table Expedition Hub</h6>
                <p class="small text-muted mb-2">
                    Meja pintar <strong>Table Expedition</strong> memungkinkan pengunjung toko mengeksplorasi keunggulan produk secara interaktif:
                </p>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Standby:</strong> Menampilkan animasi panduan letak produk dan video lanskap petualangan EIGER.</li>
                    <li><strong>Deteksi Produk:</strong> Saat produk diletakkan di atas meja, RFID reader seketika memicu tampilan 3D/video, spesifikasi teknis, serta ringkasan AI.</li>
                    <li><strong>Komparasi:</strong> Pengunjung dapat memilih produk serupa untuk membandingkan spesifikasi berdampingan secara langsung di layar meja.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Bottom Action Bar --}}
<div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
    <a href="{{ route('admin.table-expedition.index') }}" class="btn btn-light px-4">Batal</a>
    <button type="submit" class="btn btn-eiger fw-bold px-4 shadow-sm">
        <i class="bi bi-check-lg me-1"></i>{{ isset($item) ? 'Simpan Perubahan Mapping' : 'Simpan Mapping Table Expedition' }}
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle manual RFID input vs master select
    const btn = document.querySelector('.toggle-rfid-manual-btn');
    if (btn) {
        btn.addEventListener('click', function () {
            const parent = this.closest('.col-12');
            if (!parent) return;
            const selectWrap = parent.querySelector('.rfid-select-wrap');
            const manualWrap = parent.querySelector('.rfid-manual-wrap');
            const selectField = parent.querySelector('.rfid-select-field');
            const manualField = parent.querySelector('.rfid-manual-field');

            const isManualActive = !manualWrap.classList.contains('d-none');

            if (isManualActive) {
                manualWrap.classList.add('d-none');
                manualField.setAttribute('disabled', 'disabled');
                manualField.removeAttribute('name');
                manualField.removeAttribute('required');

                selectWrap.classList.remove('d-none');
                selectField.removeAttribute('disabled');
                selectField.setAttribute('name', 'rfid_tag');
                selectField.setAttribute('required', 'required');

                this.innerHTML = '<i class="bi bi-pencil-square"></i> Input Manual';
            } else {
                selectWrap.classList.add('d-none');
                selectField.setAttribute('disabled', 'disabled');
                selectField.removeAttribute('name');
                selectField.removeAttribute('required');

                manualWrap.classList.remove('d-none');
                manualField.removeAttribute('disabled');
                manualField.setAttribute('name', 'rfid_tag');
                manualField.setAttribute('required', 'required');
                manualField.focus();

                this.innerHTML = '<i class="bi bi-list-ul"></i> Pilih dari Master';
            }
        });
    }

    // 3. Search & Filter Available Similar Products
    const searchInput = document.getElementById('search-avail-sim-products');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            let count = 0;
            document.querySelectorAll('#available-sim-products-list .product-item-row').forEach(row => {
                const name = row.dataset.name;
                const sku = row.dataset.sku;
                const zone = row.dataset.zone;
                const match = (name.includes(q) || sku.includes(q) || zone.includes(q));
                row.style.display = match ? '' : 'none';
                if (match) count++;
            });
            const countEl = document.getElementById('avail-sim-count');
            if (countEl) countEl.textContent = `${count} produk`;
        });
    }

    // 4. Add Similar Product (Max 5)
    window.addSimilarProduct = function (productId) {
        const currentInputs = document.querySelectorAll('#similar-inputs-container input');
        if (currentInputs.length >= 5) {
            alert('Maksimal 5 produk rekomendasi & komparasi yang dapat dipilih.');
            return;
        }

        const mainProdSelect = document.getElementById('main_product_select');
        const mainProdId = mainProdSelect ? parseInt(mainProdSelect.value || 0) : 0;
        if (productId === mainProdId) {
            alert('Produk ini sedang dipilih sebagai Produk Utama.');
            return;
        }

        if (document.getElementById(`sim-input-${productId}`)) return;

        const row = document.getElementById(`avail-sim-prod-${productId}`);
        if (!row) return;

        const name = row.dataset.rawname;
        const sku = row.dataset.rawsku;
        const price = row.dataset.price;
        const img = row.dataset.img;

        // 1. Add hidden input
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'similar_product_ids[]';
        input.value = productId;
        input.id = `sim-input-${productId}`;
        document.getElementById('similar-inputs-container').appendChild(input);

        // 2. Add visual card
        const card = document.createElement('div');
        card.className = 'selected-sim-item p-2 mb-2 bg-white rounded border shadow-sm d-flex align-items-center justify-content-between gap-2';
        card.id = `selected-sim-card-${productId}`;
        card.dataset.id = productId;
        card.innerHTML = `
            <div class="d-flex align-items-center gap-2 overflow-hidden">
                <span class="badge bg-secondary font-monospace sim-rank-badge" style="width: 28px;">#</span>
                <img src="${img}" alt="${name}" class="rounded border object-fit-cover flex-shrink-0" style="width: 44px; height: 44px;">
                <div class="overflow-hidden">
                    <div class="fw-semibold small text-truncate text-dark" title="${name}">${name}</div>
                    <div class="text-muted small font-monospace">${sku} | ${price}</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" onclick="moveSimUp(${productId})" title="Geser Naik">
                    <i class="bi bi-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" onclick="moveSimDown(${productId})" title="Geser Turun">
                    <i class="bi bi-arrow-down"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="removeSimilarProduct(${productId})" title="Hapus">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `;

        document.getElementById('selected-similar-list').appendChild(card);
        updateSimRanks();
        updateAvailableSimState();
    };

    // 5. Remove Similar Product
    window.removeSimilarProduct = function (productId) {
        const input = document.getElementById(`sim-input-${productId}`);
        if (input) input.remove();

        const card = document.getElementById(`selected-sim-card-${productId}`);
        if (card) card.remove();

        updateSimRanks();
        updateAvailableSimState();
    };

    // 6. Move Similar Product Up
    window.moveSimUp = function (productId) {
        const card = document.getElementById(`selected-sim-card-${productId}`);
        const input = document.getElementById(`sim-input-${productId}`);
        if (!card || !card.previousElementSibling || card.previousElementSibling.id === 'sim-empty-state') return;

        const prevCard = card.previousElementSibling;
        const prevInput = input.previousElementSibling;

        card.parentNode.insertBefore(card, prevCard);
        input.parentNode.insertBefore(input, prevInput);
        updateSimRanks();
    };

    // 7. Move Similar Product Down
    window.moveSimDown = function (productId) {
        const card = document.getElementById(`selected-sim-card-${productId}`);
        const input = document.getElementById(`sim-input-${productId}`);
        if (!card || !card.nextElementSibling) return;

        const nextCard = card.nextElementSibling;
        const nextInput = input.nextElementSibling;

        card.parentNode.insertBefore(nextCard, card);
        input.parentNode.insertBefore(nextInput, input);
        updateSimRanks();
    };

    // 8. Update Ranks, Badges & Empty State
    function updateSimRanks() {
        const cards = document.querySelectorAll('#selected-similar-list .selected-sim-item');
        cards.forEach((card, index) => {
            const badge = card.querySelector('.sim-rank-badge');
            if (badge) badge.textContent = `#${index + 1}`;
        });

        const emptyState = document.getElementById('sim-empty-state');
        if (emptyState) {
            emptyState.classList.toggle('d-none', cards.length > 0);
        }

        const badge = document.getElementById('sim-count-badge');
        if (badge) {
            badge.textContent = `${cards.length} / 5 Terpilih`;
            if (cards.length >= 5) {
                badge.className = 'badge bg-success';
            } else {
                badge.className = 'badge bg-secondary';
            }
        }
    }

    // 9. Update Available Catalog Items State
    function updateAvailableSimState() {
        const mainProdSelect = document.getElementById('main_product_select');
        const mainProdId = mainProdSelect ? parseInt(mainProdSelect.value || 0) : 0;
        const simIds = Array.from(document.querySelectorAll('#similar-inputs-container input'))
            .map(inp => parseInt(inp.value));
        const isMax = simIds.length >= 5;

        document.querySelectorAll('#available-sim-products-list .product-item-row').forEach(row => {
            const id = parseInt(row.dataset.id);
            const isSim = simIds.includes(id);
            const isMain = (id === mainProdId);
            const btn = row.querySelector('.btn-add-sim');

            if (isSim || isMain) {
                row.classList.add('opacity-50');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = isMain ? '<i class="bi bi-star-fill"></i> Utama' : '<i class="bi bi-check"></i> Ada';
                }
            } else if (isMax) {
                row.classList.remove('opacity-50');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="bi bi-slash-circle"></i> Penuh';
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

    // 10. Re-evaluate when main product changes
    const mainProdSelect = document.getElementById('main_product_select');
    if (mainProdSelect) {
        mainProdSelect.addEventListener('change', function () {
            const newMainId = parseInt(this.value || 0);
            if (document.getElementById(`sim-input-${newMainId}`)) {
                removeSimilarProduct(newMainId);
            }
            updateAvailableSimState();
        });
    }

    // Run initial state setup
    updateSimRanks();
    updateAvailableSimState();
});
</script>
@endpush
