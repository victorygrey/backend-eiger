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

    <!-- PIM Enrichment Card: Technology, Activity, Specification, Custom Attributes -->
    <div id="pim-enrichment-card" class="card border-0 shadow-sm rounded-3 mt-3 mb-3 {{ (isset($product) && !empty($product->pim_payload)) ? '' : 'd-none' }}" style="background: #ffffff;">
        <div class="card-header bg-white border-bottom py-2 d-flex align-items-center justify-content-between">
            <span class="fw-bold small text-dark"><i class="bi bi-stars text-warning me-2"></i>PIM Enrichment Master Data</span>
            <span class="badge bg-primary bg-opacity-10 text-primary small" id="pim-enrichment-status">Terverifikasi 1:1 PIM</span>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <!-- Column 1: Technology & Activities -->
                <div class="col-md-6 border-end">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-cpu me-1 text-primary"></i>Teknologi Produk (Technologies)</label>
                        <div id="pim-tech-container" class="d-flex flex-column gap-2">
                            @if(isset($product) && !empty($product->technologies))
                                @foreach($product->technologies as $tech)
                                    <div class="p-2 border rounded-2 bg-light bg-opacity-50">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="badge bg-primary text-white fw-bold">{{ $tech['name'] ?? 'TEKNOLOGI' }}</span>
                                        </div>
                                        <div class="small text-muted mt-1">{{ $tech['description'] ?? '' }}</div>
                                    </div>
                                @endforeach
                            @else
                                <span class="text-muted small fst-italic">Belum ada data teknologi.</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-activity me-1 text-danger"></i>Aktivitas & Ketahanan (Activities & Ratings)</label>
                        <div id="pim-activity-container" class="d-flex flex-column gap-2">
                            @if(isset($product) && !empty($product->activities))
                                @foreach($product->activities as $act)
                                    <div class="p-2 border rounded-2 bg-light bg-opacity-50">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="fw-semibold small text-dark">{{ $act['name'] ?? 'Aktivitas' }}</span>
                                            @if(isset($act['rating']) && $act['rating'] > 0)
                                                <span class="badge bg-warning text-dark"><i class="bi bi-star-fill text-warning me-1"></i>{{ $act['desc_rating'] ?? ($act['rating'] . '/5') }}</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted">{{ $act['description'] ?? '' }}</div>
                                    </div>
                                @endforeach
                            @else
                                <span class="text-muted small fst-italic">Belum ada data aktivitas.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Column 2: Specifications & Custom Attributes -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-rulers me-1 text-success"></i>Spesifikasi Fisik (Specifications)</label>
                        <div id="pim-spec-container" class="row g-2">
                            @if(isset($product) && !empty($product->specifications))
                                @foreach($product->specifications as $spec)
                                    <div class="col-6">
                                        <div class="p-2 border rounded-2 bg-light bg-opacity-50 text-center">
                                            <div class="text-muted text-uppercase" style="font-size: 0.72rem;">{{ $spec['name'] ?? $spec['code'] }}</div>
                                            <div class="fw-bold small text-dark">{{ $spec['value'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="col-12"><span class="text-muted small fst-italic">Belum ada data spesifikasi.</span></div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-tags me-1 text-info"></i>Atribut Tambahan (Custom Attributes)</label>
                        <div id="pim-attr-container" class="table-responsive border rounded-2 bg-light bg-opacity-25" style="max-height: 180px;">
                            <table class="table table-sm table-borderless mb-0 small">
                                <tbody>
                                    @if(isset($product) && !empty($product->custom_attributes_list))
                                        @foreach($product->custom_attributes_list as $ca)
                                            <tr class="border-bottom border-light">
                                                <th class="text-muted ps-2 py-1" style="width: 40%;">{{ $ca['attributeCode'] ?? '' }}</th>
                                                <td class="text-dark pe-2 py-1 fw-semibold">{{ strip_tags($ca['value'] ?? '-') }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td class="text-muted ps-2 py-1 fst-italic">Belum ada atribut kustom.</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    function renderPimEnrichment(p) {
        const card = el('pim-enrichment-card');
        if (!card) return;

        const techContainer = el('pim-tech-container');
        const actContainer = el('pim-activity-container');
        const specContainer = el('pim-spec-container');
        const attrContainer = el('pim-attr-container');

        if (!p || (!p.technology?.length && !p.activity?.length && !p.specification?.length && !p.customAtributes?.length)) {
            card.classList.add('d-none');
            return;
        }

        card.classList.remove('d-none');

        // Render Tech
        if (techContainer) {
            const techs = p.technology || [];
            if (techs.length > 0) {
                techContainer.innerHTML = techs.map(t => `
                    <div class="p-2 border rounded-2 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="badge bg-primary text-white fw-bold">${escapeHtml(t.name || 'TEKNOLOGI')}</span>
                        </div>
                        <div class="small text-muted mt-1">${escapeHtml(t.description || '')}</div>
                    </div>
                `).join('');
            } else {
                techContainer.innerHTML = '<span class="text-muted small fst-italic">Belum ada data teknologi.</span>';
            }
        }

        // Render Activities
        if (actContainer) {
            const acts = p.activity || [];
            if (acts.length > 0) {
                actContainer.innerHTML = acts.map(a => `
                    <div class="p-2 border rounded-2 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-semibold small text-dark">${escapeHtml(a.name || 'Aktivitas')}</span>
                            ${a.rating ? `<span class="badge bg-warning text-dark"><i class="bi bi-star-fill text-warning me-1"></i>${escapeHtml(a.desc_rating || (a.rating + '/5'))}</span>` : ''}
                        </div>
                        <div class="small text-muted">${escapeHtml(a.description || '')}</div>
                    </div>
                `).join('');
            } else {
                actContainer.innerHTML = '<span class="text-muted small fst-italic">Belum ada data aktivitas.</span>';
            }
        }

        // Render Specifications
        if (specContainer) {
            const specs = p.specification || [];
            if (specs.length > 0) {
                specContainer.innerHTML = specs.map(s => `
                    <div class="col-6">
                        <div class="p-2 border rounded-2 bg-light bg-opacity-50 text-center">
                            <div class="text-muted text-uppercase" style="font-size: 0.72rem;">${escapeHtml(s.name || s.code || '')}</div>
                            <div class="fw-bold small text-dark">${escapeHtml(s.value || '-')}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                specContainer.innerHTML = '<div class="col-12"><span class="text-muted small fst-italic">Belum ada data spesifikasi.</span></div>';
            }
        }

        // Render Custom Attributes
        if (attrContainer) {
            const attrs = p.customAtributes || [];
            if (attrs.length > 0) {
                attrContainer.innerHTML = `
                    <table class="table table-sm table-borderless mb-0 small">
                        <tbody>
                            ${attrs.map(ca => `
                                <tr class="border-bottom border-light">
                                    <th class="text-muted ps-2 py-1" style="width: 40%;">${escapeHtml(ca.attributeCode || '')}</th>
                                    <td class="text-dark pe-2 py-1 fw-semibold">${escapeHtml(String(ca.value || '-')).replace(/<[^>]*>?/gm, '')}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                attrContainer.innerHTML = '<span class="text-muted small fst-italic p-2 d-block">Belum ada atribut kustom.</span>';
            }
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

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
            if (field('zone_id') && data.zone_id) field('zone_id').value = data.zone_id;
            if (field('image') && data.image) field('image').value = data.image;
            if (field('price') && data.price !== undefined) field('price').value = data.price;
            if (field('stock') && data.stock !== undefined) field('stock').value = data.stock;

            // Populate PIM Payload textareas
            if (el('pim-payload') && data.pim_payload) {
                el('pim-payload').value = JSON.stringify(data.pim_payload, null, 2);
            }
            if (el('pim-images') && data.pim_image_payload) {
                el('pim-images').value = JSON.stringify(data.pim_image_payload, null, 2);
            }

            // Render PIM Enrichment Visual Cards
            if (data.pim_payload) {
                renderPimEnrichment(data.pim_payload);
            }

            // Populate Cover Photo Picker & PIM Gallery
            const allImages = Array.isArray(data.images) && data.images.length > 0
                ? data.images
                : (data.image ? [data.image] : []);

            if (typeof window.updateAvailablePimPhotos === 'function') {
                window.updateAvailablePimPhotos(allImages, data.image);
            }

            // Populate Variants Table
            if (Array.isArray(data.variants) && typeof window.populateCatalogVariants === 'function') {
                window.populateCatalogVariants(data.variants);
            }

            feedback.className = 'small text-success fw-semibold mb-2';
            feedback.textContent = `✓ Data artikel "${data.name}", ${allImages.length} foto, master enrichment (Teknologi, Aktivitas, Spesifikasi), dan ${data.variants.length} varian berhasil disinkronkan dari PIM & CARE!`;
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
