<div class="card mt-4 border rounded-3 shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
        <span class="fw-bold text-dark">
            <i class="bi bi-diagram-3-fill text-primary me-2"></i>Daftar Varian Produk (SKU 12-Digit, Warna, Ukuran, Harga & Stok CARE)
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
                        <th style="width: 25%;">SKU Varian (12 Digit) <span class="text-danger">*</span></th>
                        <th style="width: 15%;">Warna</th>
                        <th style="width: 15%;">Ukuran</th>
                        <th style="width: 20%;">Harga Varian (Rp)</th>
                        <th style="width: 15%;">Stok Toko</th>
                        <th style="width: 10%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="variants-tbody">
                    @php
                        $existingVariants = old('variants', isset($product) && $product->variants ? $product->variants->toArray() : []);
                    @endphp
                    @forelse($existingVariants as $index => $v)
                        <tr class="variant-row" data-index="{{ $index }}">
                            <td>
                                <input type="text" name="variants[{{ $index }}][sku]" value="{{ $v['sku'] ?? '' }}"
                                    class="form-control form-control-sm font-monospace fw-bold" placeholder="Contoh: 910009029001" required>
                                <input type="hidden" name="variants[{{ $index }}][name]" value="{{ $v['name'] ?? '' }}" class="variant-name">
                            </td>
                            <td>
                                <input type="text" name="variants[{{ $index }}][color]" value="{{ $v['color'] ?? '' }}"
                                    class="form-control form-control-sm" placeholder="Warna">
                            </td>
                            <td>
                                <input type="text" name="variants[{{ $index }}][size]" value="{{ $v['size'] ?? '' }}"
                                    class="form-control form-control-sm" placeholder="Ukuran">
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
                            <td colspan="6" class="text-center py-3 text-muted small">
                                Belum ada varian ditambahkan. Klik "Tambah Baris Varian" atau gunakan fitur "Tarik Data PIM & CARE" di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('variants-tbody');
    const btnAdd = document.getElementById('btn-add-variant');
    let variantIndex = {{ count($existingVariants) }};

    function recalculateParentStock() {
        let total = 0;
        document.querySelectorAll('.variant-stock').forEach(el => {
            const val = parseInt(el.value, 10);
            if (!isNaN(val) && val > 0) total += val;
        });
        const parentStockInput = document.querySelector('input[name="stock"]');
        if (parentStockInput && total > 0) {
            parentStockInput.value = total;
        }
    }

    tbody.addEventListener('input', (e) => {
        if (e.target.classList.contains('variant-stock')) {
            recalculateParentStock();
        }
    });

    btnAdd.addEventListener('click', () => {
        const noVarRow = document.getElementById('row-no-variants');
        if (noVarRow) noVarRow.remove();

        const parentSku = (document.querySelector('input[name="sku"]')?.value || 'SKU').trim();
        const parentPrice = document.querySelector('input[name="price"]')?.value || 0;
        const seq = String(tbody.querySelectorAll('.variant-row').length + 1).padStart(3, '0');
        const defaultSku = parentSku.length === 9 ? (parentSku + seq) : '';

        const tr = document.createElement('tr');
        tr.className = 'variant-row';
        tr.dataset.index = variantIndex;
        tr.innerHTML = `
            <td>
                <input type="text" name="variants[${variantIndex}][sku]" value="${defaultSku}"
                    class="form-control form-control-sm font-monospace fw-bold" placeholder="SKU Varian 12 Digit" required>
                <input type="hidden" name="variants[${variantIndex}][name]" value="" class="variant-name">
            </td>
            <td>
                <input type="text" name="variants[${variantIndex}][color]" value=""
                    class="form-control form-control-sm" placeholder="Warna">
            </td>
            <td>
                <input type="text" name="variants[${variantIndex}][size]" value=""
                    class="form-control form-control-sm" placeholder="Ukuran">
            </td>
            <td>
                <input type="number" name="variants[${variantIndex}][price]" value="${parentPrice}"
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

    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-remove-variant');
        if (btn) {
            btn.closest('tr').remove();
            recalculateParentStock();
            if (tbody.querySelectorAll('.variant-row').length === 0) {
                tbody.innerHTML = `
                    <tr id="row-no-variants">
                        <td colspan="6" class="text-center py-3 text-muted small">
                            Belum ada varian ditambahkan. Klik "Tambah Baris Varian" atau gunakan fitur "Tarik Data PIM & CARE" di atas.
                        </td>
                    </tr>
                `;
            }
        }
    });

    // Expose helper to inject variants from catalog lookup
    window.populateCatalogVariants = function(variants) {
        if (!Array.isArray(variants) || variants.length === 0) return;
        tbody.replaceChildren();
        variantIndex = 0;

        const parentSku = (document.querySelector('input[name="sku"]')?.value || '').trim();
        const parentName = (document.querySelector('input[name="name"]')?.value || '').trim();
        let seq = 1;

        // Flatten any variant with comma-separated sizes into individual rows
        const cleanVariants = [];
        variants.forEach(v => {
            const rawSize = String(v.size || '').trim();
            const rawSizes = rawSize.includes(',') ? rawSize.split(',').map(s => s.trim()).filter(Boolean) : [rawSize];
            const baseColor = v.color || '';

            rawSizes.forEach(sz => {
                let sku12 = String(v.sku || '').trim();
                // If sku is not 12 digits or if size was split, assign proper 12-digit SKU
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
                });
            });
        });

        cleanVariants.forEach(v => {
            const tr = document.createElement('tr');
            tr.className = 'variant-row';
            tr.dataset.index = variantIndex;
            tr.innerHTML = `
                <td>
                    <input type="text" name="variants[${variantIndex}][sku]" value="${v.sku || ''}"
                        class="form-control form-control-sm font-monospace fw-bold" placeholder="SKU 12-Digit" required>
                    <input type="hidden" name="variants[${variantIndex}][name]" value="${v.name || ''}" class="variant-name">
                </td>
                <td>
                    <input type="text" name="variants[${variantIndex}][color]" value="${v.color || ''}"
                        class="form-control form-control-sm" placeholder="Warna">
                </td>
                <td>
                    <input type="text" name="variants[${variantIndex}][size]" value="${v.size || ''}"
                        class="form-control form-control-sm" placeholder="Ukuran">
                </td>
                <td>
                    <input type="number" name="variants[${variantIndex}][price]" value="${v.price || 0}"
                        step="0.01" min="0" class="form-control form-control-sm text-end">
                </td>
                <td>
                    <input type="number" name="variants[${variantIndex}][stock]" value="${v.stock || 0}"
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
});
</script>
