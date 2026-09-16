<div class="card mt-4 border rounded-3 shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
        <span class="fw-bold text-dark">
            <i class="bi bi-diagram-3-fill text-primary me-2"></i>Daftar Varian Produk (SKU 12-Digit, Foto, Warna, Ukuran, Harga & Stok CARE)
        </span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-variant">
            <i class="bi bi-plus-circle me-1"></i>Tambah Baris Varian
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="table-variants">
                <thead class="table-light small text-muted">
                    <tr>
                        <th style="width: 22%;">SKU Varian (12 Digit) <span class="text-danger">*</span></th>
                        <th style="width: 10%;" class="text-center">Foto Varian</th>
                        <th style="width: 14%;">Warna</th>
                        <th style="width: 14%;">Ukuran</th>
                        <th style="width: 18%;">Harga Varian (Rp)</th>
                        <th style="width: 12%;">Stok Toko</th>
                        <th style="width: 10%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="variants-tbody">
                    @php
                        $existingVariants = old('variants', isset($product) && $product->variants ? $product->variants->toArray() : []);
                        $parentCover = old('image', isset($product) ? $product->image : '');
                    @endphp
                    @forelse($existingVariants as $index => $v)
                        @php
                            $vPhoto = $v['image'] ?? '';
                            $displayPhoto = \App\Support\PimMediaUrl::toPublicUrl($vPhoto ?: $parentCover);
                        @endphp
                        <tr class="variant-row" data-index="{{ $index }}">
                            <td>
                                <input type="text" name="variants[{{ $index }}][sku]" value="{{ $v['sku'] ?? '' }}"
                                    class="form-control form-control-sm font-monospace fw-bold variant-sku-input" placeholder="Contoh: 910009029001" required>
                                <input type="hidden" name="variants[{{ $index }}][name]" value="{{ $v['name'] ?? '' }}" class="variant-name">
                            </td>
                            <td class="text-center align-middle">
                                <div class="position-relative d-inline-block variant-photo-box" data-index="{{ $index }}">
                                    <img src="{{ $displayPhoto ?: 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2240%22%20height%3D%2240%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2240%22%20height%3D%2240%22%20fill%3D%22%23f3f4f6%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%239ca3af%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%228%22%3ENo%20Img%3C%2Ftext%3E%3C%2Fsvg%3E' }}"
                                         alt="Foto Varian"
                                         class="rounded border bg-white shadow-sm variant-thumb cursor-pointer"
                                         style="width: 40px; height: 40px; object-fit: contain; cursor: pointer; padding: 1px;"
                                         title="Klik untuk memilih foto varian ini"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2240%22%20height%3D%2240%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2240%22%20height%3D%2240%22%20fill%3D%22%23fee2e2%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%23ef4444%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%228%22%3EErr%3C%2Ftext%3E%3C%2Fsvg%3E';">
                                    <input type="hidden" name="variants[{{ $index }}][image]" value="{{ $vPhoto }}" class="variant-image-input">
                                    <button type="button" class="btn btn-sm btn-dark position-absolute bottom-0 end-0 p-0 d-flex align-items-center justify-content-center btn-trigger-photo-modal"
                                            style="width: 16px; height: 16px; font-size: 8px; border-radius: 50%; opacity: 0.85;"
                                            title="Pilih foto dari PIM">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <input type="text" name="variants[{{ $index }}][color]" value="{{ $v['color'] ?? '' }}"
                                    class="form-control form-control-sm variant-color-input" placeholder="Warna">
                            </td>
                            <td>
                                <input type="text" name="variants[{{ $index }}][size]" value="{{ $v['size'] ?? '' }}"
                                    class="form-control form-control-sm variant-size-input" placeholder="Ukuran">
                            </td>
                            <td>
                                <input type="number" name="variants[{{ $index }}][price]" value="{{ $v['price'] ?? 0 }}"
                                    step="0.01" min="0" class="form-control form-control-sm text-end" placeholder="0">
                            </td>
                            <td>
                                <input type="number" name="variants[{{ $index }}][stock]" value="{{ $v['stock'] ?? 0 }}"
                                    min="0" class="form-control form-control-sm text-center variant-stock" placeholder="0">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-remove-variant" title="Hapus varian">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr id="row-no-variants">
                            <td colspan="7" class="text-center py-3 text-muted small">
                                Belum ada varian ditambahkan. Klik "Tambah Baris Varian" atau gunakan fitur "Tarik Data PIM & CARE" di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Pemilihan Foto Varian dari Galeri PIM --}}
<div class="modal fade" id="modal-variant-photo-picker" tabindex="-1" aria-labelledby="modalVariantPhotoPickerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2 px-3 bg-light border-bottom">
                <h6 class="modal-title fw-bold text-dark" id="modalVariantPhotoPickerLabel">
                    <i class="bi bi-images text-primary me-2"></i>Pilih Foto untuk Varian <span id="modal-target-variant-title" class="badge bg-dark font-monospace ms-1"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <p class="small text-muted mb-2">
                    <i class="bi bi-hand-index-thumb me-1"></i>Klik salah satu foto produk di bawah ini untuk dipasangkan pada varian ini:
                </p>
                <div id="modal-pim-photos-grid" class="d-flex flex-wrap gap-2 p-2 border rounded-3 bg-light mb-3" style="max-height: 250px; overflow-y: auto;">
                    <!-- Diisi secara dinamis dari window.availablePimPhotos -->
                </div>
                <div class="border-top pt-2">
                    <label class="form-label small fw-semibold mb-1 text-muted">Atau masukkan URL foto khusus secara manual:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-link-45deg"></i></span>
                        <input type="text" id="modal-variant-manual-url" class="form-control font-monospace" placeholder="https://... atau http://192.168.18.31:8001/media/...">
                        <button type="button" class="btn btn-primary btn-sm px-3" id="btn-save-modal-variant-url">Terapkan Foto</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between bg-light border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-use-cover-for-variant">
                    <i class="bi bi-star-fill text-warning me-1"></i>Samakan dengan Foto Cover
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('variants-tbody');
    const btnAdd = document.getElementById('btn-add-variant');
    let variantIndex = {{ count($existingVariants) }};
    function escapeVariantAttribute(value) {
        return String(value ?? '').replaceAll('&', '&amp;').replaceAll('"', '&quot;')
            .replaceAll('<', '&lt;').replaceAll('>', '&gt;');
    }

    // Modal picker references
    const modalEl = document.getElementById('modal-variant-photo-picker');
    const modalTitleBadge = document.getElementById('modal-target-variant-title');
    const modalGrid = document.getElementById('modal-pim-photos-grid');
    const modalManualInput = document.getElementById('modal-variant-manual-url');
    const btnSaveModalUrl = document.getElementById('btn-save-modal-variant-url');
    const btnUseCover = document.getElementById('btn-use-cover-for-variant');
    let activeTargetBox = null;

    function recalculateParentStock() {
        let total = 0;
        document.querySelectorAll('.variant-stock').forEach(el => {
            const val = parseInt(el.value, 10);
            if (!isNaN(val) && val > 0) total += val;
        });
        const parentStockInput = document.querySelector('input[name="stock"]');
        if (parentStockInput) {
            parentStockInput.value = total;
        }
    }

    tbody.addEventListener('input', (e) => {
        if (e.target.classList.contains('variant-stock')) {
            recalculateParentStock();
        }
    });

    // Helper untuk mengambil foto cover aktif
    function getActiveCoverPhoto() {
        const inputHidden = document.getElementById('hidden-cover-image');
        const inputManual = document.getElementById('input-cover-image');
        return (inputHidden && inputHidden.value) || (inputManual && inputManual.value) || '';
    }

    // Modal picker logic
    function openVariantPhotoModal(box) {
        activeTargetBox = box;
        const row = box.closest('tr');
        const skuInput = row.querySelector('.variant-sku-input');
        const colorInput = row.querySelector('.variant-color-input');
        const skuText = (skuInput && skuInput.value.trim()) || 'Baru';
        const colorText = (colorInput && colorInput.value.trim()) || '';

        modalTitleBadge.textContent = colorText ? `${skuText} (${colorText})` : skuText;

        const currentVal = box.querySelector('.variant-image-input')?.value || '';
        modalManualInput.value = currentVal;

        // Render photo grid
        renderModalPhotosGrid(currentVal);

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }

    function renderModalPhotosGrid(currentSelectedUrl) {
        modalGrid.replaceChildren();
        const photos = window.availablePimPhotos || [];

        if (photos.length === 0) {
            modalGrid.innerHTML = `
                <div class="w-100 text-center py-3 text-muted small">
                    <i class="bi bi-info-circle me-1"></i>Belum ada foto produk dari PIM. Klik "Tarik Data PIM & CARE" pada formulir atas terlebih dahulu atau masukkan URL manual di bawah.
                </div>
            `;
            return;
        }

        photos.forEach((url, idx) => {
            const isMatch = url === currentSelectedUrl;
            const item = document.createElement('div');
            item.className = `modal-photo-item position-relative rounded border ${isMatch ? 'border-primary border-3 shadow-sm' : 'border-secondary border-opacity-25'} p-1 bg-white cursor-pointer`;
            item.style.cssText = 'cursor: pointer; width: 68px; height: 68px; transition: all 0.15s;';
            item.title = `Pilih foto ini (Foto ${idx + 1})`;
            item.innerHTML = `
                <img src="${escapeVariantAttribute(url)}" alt="PIM ${idx + 1}" class="w-100 h-100 rounded" style="object-fit: contain;" loading="lazy"
                     onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2260%22%20height%3D%2260%22%20fill%3D%22%23f3f4f6%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%239ca3af%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%229%22%3EImg%3C%2Ftext%3E%3C%2Fsvg%3E';">
                ${isMatch ? '<span class="position-absolute top-0 end-0 badge bg-primary p-1" style="font-size: 8px;"><i class="bi bi-check-lg"></i></span>' : ''}
            `;
            item.addEventListener('click', () => {
                applyPhotoToActiveVariant(url);
            });
            modalGrid.appendChild(item);
        });
    }

    function applyPhotoToActiveVariant(url) {
        if (!activeTargetBox) return;
        const img = activeTargetBox.querySelector('.variant-thumb');
        const input = activeTargetBox.querySelector('.variant-image-input');
        if (input) input.value = url;
        if (img && url) img.src = url;

        const bsModal = bootstrap.Modal.getInstance(modalEl);
        if (bsModal) bsModal.hide();
    }

    // Event listener modal action buttons
    btnSaveModalUrl.addEventListener('click', () => {
        const val = modalManualInput.value.trim();
        if (val) {
            applyPhotoToActiveVariant(val);
        }
    });

    btnUseCover.addEventListener('click', () => {
        const cover = getActiveCoverPhoto();
        if (cover) {
            applyPhotoToActiveVariant(cover);
        }
    });

    // Delegasi klik thumbnail foto varian di tabel
    tbody.addEventListener('click', (e) => {
        const trigger = e.target.closest('.variant-thumb, .btn-trigger-photo-modal');
        if (trigger) {
            const box = trigger.closest('.variant-photo-box');
            if (box) openVariantPhotoModal(box);
            return;
        }

        const btn = e.target.closest('.btn-remove-variant');
        if (btn) {
            btn.closest('tr').remove();
            recalculateParentStock();
            if (tbody.querySelectorAll('.variant-row').length === 0) {
                tbody.innerHTML = `
                    <tr id="row-no-variants">
                        <td colspan="7" class="text-center py-3 text-muted small">
                            Belum ada varian ditambahkan. Klik "Tambah Baris Varian" atau gunakan fitur "Tarik Data PIM & CARE" di atas.
                        </td>
                    </tr>
                `;
            }
        }
    });

    // Tambah baris varian manual
    btnAdd.addEventListener('click', () => {
        const noVarRow = document.getElementById('row-no-variants');
        if (noVarRow) noVarRow.remove();

        const parentSku = (document.querySelector('input[name="sku"]')?.value || 'SKU').trim();
        const parentPrice = document.querySelector('input[name="price"]')?.value || 0;
        const coverPhoto = getActiveCoverPhoto();
        const seq = String(tbody.querySelectorAll('.variant-row').length + 1).padStart(3, '0');
        const defaultSku = parentSku.length === 9 ? (parentSku + seq) : '';

        const tr = document.createElement('tr');
        tr.className = 'variant-row';
        tr.dataset.index = variantIndex;
        tr.innerHTML = `
            <td>
                <input type="text" name="variants[${variantIndex}][sku]" value="${escapeVariantAttribute(defaultSku)}"
                    class="form-control form-control-sm font-monospace fw-bold variant-sku-input" placeholder="SKU Varian 12 Digit" required>
                <input type="hidden" name="variants[${variantIndex}][name]" value="" class="variant-name">
            </td>
            <td class="text-center align-middle">
                <div class="position-relative d-inline-block variant-photo-box" data-index="${variantIndex}">
                    <img src="${escapeVariantAttribute(coverPhoto || 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2240%22%20height%3D%2240%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2240%22%20height%3D%2240%22%20fill%3D%22%23f3f4f6%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%239ca3af%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%228%22%3ENo%20Img%3C%2Ftext%3E%3C%2Fsvg%3E')}"
                         alt="Foto Varian"
                         class="rounded border bg-white shadow-sm variant-thumb cursor-pointer"
                         style="width: 40px; height: 40px; object-fit: contain; cursor: pointer; padding: 1px;"
                         title="Klik untuk memilih foto varian ini"
                         onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2240%22%20height%3D%2240%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2240%22%20height%3D%2240%22%20fill%3D%22%23fee2e2%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%23ef4444%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%228%22%3EErr%3C%2Ftext%3E%3C%2Fsvg%3E';">
                    <input type="hidden" name="variants[${variantIndex}][image]" value="${escapeVariantAttribute(coverPhoto)}" class="variant-image-input">
                    <button type="button" class="btn btn-sm btn-dark position-absolute bottom-0 end-0 p-0 d-flex align-items-center justify-content-center btn-trigger-photo-modal"
                            style="width: 16px; height: 16px; font-size: 8px; border-radius: 50%; opacity: 0.85;"
                            title="Pilih foto dari PIM">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                </div>
            </td>
            <td>
                <input type="text" name="variants[${variantIndex}][color]" value=""
                    class="form-control form-control-sm variant-color-input" placeholder="Warna">
            </td>
            <td>
                <input type="text" name="variants[${variantIndex}][size]" value=""
                    class="form-control form-control-sm variant-size-input" placeholder="Ukuran">
            </td>
            <td>
                <input type="number" name="variants[${variantIndex}][price]" value="${escapeVariantAttribute(parentPrice)}"
                    step="0.01" min="0" class="form-control form-control-sm text-end" placeholder="0">
            </td>
            <td>
                <input type="number" name="variants[${variantIndex}][stock]" value="0"
                    min="0" class="form-control form-control-sm text-center variant-stock" placeholder="0">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-remove-variant" title="Hapus varian">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        variantIndex++;
    });

    // Injeksi varian otomatis saat "Tarik Data PIM & CARE"
    window.populateCatalogVariants = function(variants) {
        if (!Array.isArray(variants) || variants.length === 0) return;
        tbody.replaceChildren();
        variantIndex = 0;

        const parentSku = (document.querySelector('input[name="sku"]')?.value || '').trim();
        const parentName = (document.querySelector('input[name="name"]')?.value || '').trim();
        const coverPhoto = getActiveCoverPhoto();
        let seq = 1;

        // Flatten any variant with comma-separated sizes into individual rows
        const cleanVariants = [];
        variants.forEach(v => {
            const rawSize = String(v.size || '').trim();
            const rawSizes = rawSize.includes(',') ? rawSize.split(',').map(s => s.trim()).filter(Boolean) : [rawSize];
            const baseColor = v.color || '';

            rawSizes.forEach(sz => {
                let sku12 = String(v.sku || '').trim();
                if (sku12.length !== 12 || rawSizes.length > 1) {
                    if (parentSku.length === 9) {
                        sku12 = parentSku + String(seq).padStart(3, '0');
                    }
                }
                seq++;

                cleanVariants.push({
                    sku: sku12,
                    name: v.name || `${parentName} - ${baseColor} - ${sz}`,
                    color: baseColor,
                    size: sz,
                    price: v.price || 0,
                    stock: Math.round((v.stock || 0) / (rawSizes.length > 1 ? rawSizes.length : 1)),
                    image: v.image || coverPhoto || '',
                });
            });
        });

        cleanVariants.forEach(v => {
            const vImg = v.image || coverPhoto;
            const tr = document.createElement('tr');
            tr.className = 'variant-row';
            tr.dataset.index = variantIndex;
            tr.innerHTML = `
                <td>
                    <input type="text" name="variants[${variantIndex}][sku]" value="${escapeVariantAttribute(v.sku)}"
                        class="form-control form-control-sm font-monospace fw-bold variant-sku-input" placeholder="SKU 12-Digit" required>
                    <input type="hidden" name="variants[${variantIndex}][name]" value="${escapeVariantAttribute(v.name)}" class="variant-name">
                </td>
                <td class="text-center align-middle">
                    <div class="position-relative d-inline-block variant-photo-box" data-index="${variantIndex}">
                        <img src="${escapeVariantAttribute(vImg || 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2240%22%20height%3D%2240%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2240%22%20height%3D%2240%22%20fill%3D%22%23f3f4f6%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%239ca3af%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%228%22%3ENo%20Img%3C%2Ftext%3E%3C%2Fsvg%3E')}"
                             alt="Foto Varian"
                             class="rounded border bg-white shadow-sm variant-thumb cursor-pointer"
                             style="width: 40px; height: 40px; object-fit: contain; cursor: pointer; padding: 1px;"
                             title="Klik untuk memilih foto varian ini"
                             onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2240%22%20height%3D%2240%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2240%22%20height%3D%2240%22%20fill%3D%22%23fee2e2%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%23ef4444%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%228%22%3EErr%3C%2Ftext%3E%3C%2Fsvg%3E';">
                        <input type="hidden" name="variants[${variantIndex}][image]" value="${escapeVariantAttribute(vImg)}" class="variant-image-input">
                        <button type="button" class="btn btn-sm btn-dark position-absolute bottom-0 end-0 p-0 d-flex align-items-center justify-content-center btn-trigger-photo-modal"
                                style="width: 16px; height: 16px; font-size: 8px; border-radius: 50%; opacity: 0.85;"
                                title="Pilih foto dari PIM">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <input type="text" name="variants[${variantIndex}][color]" value="${escapeVariantAttribute(v.color)}"
                        class="form-control form-control-sm variant-color-input" placeholder="Warna">
                </td>
                <td>
                    <input type="text" name="variants[${variantIndex}][size]" value="${escapeVariantAttribute(v.size)}"
                        class="form-control form-control-sm variant-size-input" placeholder="Ukuran">
                </td>
                <td>
                    <input type="number" name="variants[${variantIndex}][price]" value="${escapeVariantAttribute(v.price || 0)}"
                        step="0.01" min="0" class="form-control form-control-sm text-end">
                </td>
                <td>
                    <input type="number" name="variants[${variantIndex}][stock]" value="${escapeVariantAttribute(v.stock || 0)}"
                        min="0" class="form-control form-control-sm text-center variant-stock">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-remove-variant" title="Hapus varian">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            variantIndex++;
        });
        recalculateParentStock();
    };

    // Callback saat foto PIM diperbarui oleh _cover_picker
    window.refreshVariantPhotoPicker = function() {
        // Jika ada baris varian yang masih kosong fotonya, samakan dengan cover aktif
        const activeCover = getActiveCoverPhoto();
        if (activeCover) {
            document.querySelectorAll('.variant-photo-box').forEach(box => {
                const input = box.querySelector('.variant-image-input');
                const img = box.querySelector('.variant-thumb');
                if (input && !input.value) {
                    input.value = activeCover;
                    if (img) img.src = activeCover;
                }
            });
        }
    };
});
</script>
