<div class="border rounded-3 p-3 mb-4 bg-light bg-opacity-50" id="pim-product-form"
    data-lookup="{{ route('admin.products.pim-lookup') }}"
    data-catalog-lookup="{{ route('admin.products.catalog-lookup') }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="bi bi-cloud-arrow-down-fill text-primary me-2"></i>Sinkronisasi Otomatis Data PIM & CARE OMNI
        </h6>
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">
            <i class="bi bi-check2-all me-1"></i>PIM & CARE Terintegrasi
        </span>
    </div>
    <p class="text-muted small mb-3">
        Masukkan Kode Artikel (9 Digit, contoh <code>910009029</code>) untuk menarik otomatis nama produk, deskripsi, gambar dari <strong>PIM</strong>, serta harga ritel dan stok toko Setiabudi beserta seluruh variannya dari <strong>CARE</strong>.
    </p>

    <div class="input-group mb-2">
        <span class="input-group-text bg-white"><i class="bi bi-upc-scan"></i></span>
        <input id="pim-code" class="form-control" placeholder="Masukkan Kode Artikel 9-Digit (contoh: 910009029)" aria-label="Kode artikel PIM">
        <button type="button" id="btn-auto-fetch" class="btn btn-primary text-nowrap">
            <span class="spinner-border spinner-border-sm d-none me-1" id="auto-fetch-spinner" role="status"></span>
            <i class="bi bi-magic me-1"></i>Tarik Data PIM & CARE
        </button>
    </div>
    <div id="pim-feedback" role="status" class="small mb-2"></div>

    <div id="pim-gallery" class="d-flex gap-2 overflow-auto my-2"></div>

    <details class="mt-2">
        <summary class="small text-muted mb-2">Lihat payload mentah PIM (Opsional / Debugging)</summary>
        <label class="form-label small" for="pim-payload">Detail produk (JSON)</label>
        <textarea id="pim-payload" name="pim_payload_json" class="form-control font-monospace mb-2 small" rows="6">{{ old('pim_payload_json', isset($product) && $product->pim_payload ? json_encode($product->pim_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
        <label class="form-label small" for="pim-images">Image to Channel (JSON)</label>
        <textarea id="pim-images" name="pim_image_payload_json" class="form-control font-monospace small" rows="5">{{ old('pim_image_payload_json', isset($product) && $product->pim_image_payload ? json_encode($product->pim_image_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    </details>
    @error('pim_payload_json') <div class="text-danger small">{{ $message }}</div> @enderror
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('pim-product-form');
    const form = root.closest('form');
    const el = id => document.getElementById(id);
    const field = name => form.elements.namedItem(name);

    const btnFetch = el('btn-auto-fetch');
    const spinner = el('auto-fetch-spinner');
    const feedback = el('pim-feedback');

    btnFetch.addEventListener('click', async () => {
        const code = el('pim-code').value.trim();
        if (!code) {
            feedback.className = 'small text-danger mb-2';
            feedback.textContent = 'Silakan masukkan kode artikel 9-digit terlebih dahulu.';
            return;
        }

        btnFetch.disabled = true;
        spinner.classList.remove('d-none');
        feedback.className = 'small text-muted mb-2';
        feedback.textContent = 'Menghubungi PIM dan CARE Simulator…';

        try {
            const res = await fetch(`${root.dataset.catalogLookup}?code=${encodeURIComponent(code)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || 'Gagal mengambil data katalog.');
            }

            // Populate Form Fields
            if (field('sku') && data.sku) field('sku').value = data.sku;
            if (field('name') && data.name) field('name').value = data.name;
            if (field('description') && data.description) field('description').value = data.description;
            if (field('material') && data.material) field('material').value = data.material;
            if (field('image') && data.image) field('image').value = data.image;
            if (field('price') && data.price !== undefined) field('price').value = data.price;
            if (field('stock') && data.stock !== undefined) field('stock').value = data.stock;

            // Populate Variants Table
            if (Array.isArray(data.variants) && typeof window.populateCatalogVariants === 'function') {
                window.populateCatalogVariants(data.variants);
            }

            // Preview Image
            el('pim-gallery').replaceChildren();
            if (data.image) {
                const img = document.createElement('img');
                img.src = data.image;
                img.alt = data.name;
                img.className = 'rounded border shadow-sm';
                img.style.cssText = 'width: 100px; height: 110px; object-fit: contain; background: #fff;';
                el('pim-gallery').appendChild(img);
            }

            feedback.className = 'small text-success fw-semibold mb-2';
            feedback.textContent = `✓ Data artikel "${data.name}" dan ${data.variants.length} varian berhasil disinkronkan dari PIM & CARE!`;
        } catch (e) {
            feedback.className = 'small text-danger mb-2';
            feedback.textContent = e.message;
        } finally {
            btnFetch.disabled = false;
            spinner.classList.add('d-none');
        }
    });
});
</script>
