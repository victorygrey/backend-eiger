<div class="mb-3" id="pim-product-form"
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
        const mediaContainer = el('pim-extra-media');
        const variantContainer = el('pim-variant-data');

        if (!p || (!p.technology?.length && !p.activity?.length && !p.specification?.length && !p.customAtributes?.length && !p.media?.length && !p.variant?.length)) {
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
                        <div class="d-flex gap-2 align-items-start">
                            ${mediaPreview(t.image, t.name || 'Teknologi produk', true)}
                            <div>
                                <span class="badge bg-primary text-white fw-bold">${escapeHtml(t.name || 'TEKNOLOGI')}</span>
                                <div class="small text-muted mt-1">${escapeHtml(plainText(t.description || ''))}</div>
                            </div>
                        </div>
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
                            ${a.rating ? `<span class="badge bg-warning text-dark"><i class="bi bi-star-fill text-warning me-1"></i>${escapeHtml(plainText(a.desc_rating || (a.rating + '/5')))}</span>` : ''}
                        </div>
                        <div class="small text-muted">${escapeHtml(plainText(a.description || ''))}</div>
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
                                    <td class="text-dark pe-2 py-1 fw-semibold">${escapeHtml(plainText(ca.value || '-'))}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                attrContainer.innerHTML = '<span class="text-muted small fst-italic p-2 d-block">Belum ada atribut kustom.</span>';
            }
        }
        if (mediaContainer) {
            const files = (p.media || []).flatMap(group => (group.files || []).map(file => ({group, file})));
            mediaContainer.innerHTML = files.length ? files.map(({group, file}) => `
                <div class="p-2 mb-2 border rounded-2 bg-light bg-opacity-50">
                    <div class="d-flex gap-3 align-items-start">
                        ${mediaPreview(file.value, group.name || group.attributeCode || 'Media')}
                        <div class="min-w-0">
                            <strong>${escapeHtml(group.name || group.attributeCode || 'Media')}</strong>
                            <span class="badge bg-light text-dark ms-1">${escapeHtml(group.attributeCode || '')}</span>
                            <div class="text-muted">${escapeHtml(plainText(file.description || ''))}</div>
                            ${safeMediaUrl(file.value) ? `<a href="${escapeHtml(safeMediaUrl(file.value))}" target="_blank" rel="noopener noreferrer" class="small text-break">Buka media asli <i class="bi bi-box-arrow-up-right"></i></a>` : ''}
                        </div>
                    </div>
                </div>`).join('') : '<span class="text-muted fst-italic">Belum ada media tambahan.</span>';
        }
        if (variantContainer) {
            variantContainer.innerHTML = (p.variant || []).length ? p.variant.map(v => `
                <div class="col-md-6"><div class="p-2 border rounded-2 bg-light bg-opacity-50 h-100 small">
                    <div class="fw-bold text-dark">${escapeHtml(v.name || v.sku || 'Varian')}</div>
                    <div class="font-monospace">${escapeHtml(v.sku || '')}</div>
                    <div>ECM SKU: ${escapeHtml(v.ecmsku || '—')} · MOQ: ${escapeHtml(v.moq || '—')}</div>
                    <div>Warna: ${escapeHtml(v.color || '—')} · Ukuran: ${escapeHtml(v.size || '—')}</div>
                    ${(v.customAttributes || []).map(a => `<div><span class="text-muted">${escapeHtml(a.attributeCode || '')}:</span> ${escapeHtml(plainText(a.value || '—'))}</div>`).join('')}
                </div></div>`).join('') : '<div class="col-12 text-muted fst-italic small">Belum ada metadata varian.</div>';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function plainText(value) {
        const d = document.createElement('div');
        d.innerHTML = String(value).replace(/<br\s*\/?>|<\/(?:p|div|li|h[1-6])>/gi, ' ');
        return (d.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function safeMediaUrl(value) {
        if (!value) return '';
        try {
            const url = new URL(String(value), window.location.origin);
            return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
        } catch (_) {
            return '';
        }
    }

    function mediaPreview(value, label, compact = false) {
        const url = safeMediaUrl(value);
        if (!url) return '';
        const path = new URL(url).pathname.toLowerCase();
        if (/\.(?:jpe?g|png|webp|gif|svg)$/.test(path)) {
            const size = compact ? 'width:72px;height:72px' : 'width:112px;height:82px';
            return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="flex-shrink-0"><img src="${escapeHtml(url)}" alt="${escapeHtml(label || 'Media PIM')}" class="rounded border bg-white object-fit-contain" style="${size}"></a>`;
        }
        if (/\.(?:mp4|webm|ogg)$/.test(path)) {
            return `<video controls preload="metadata" class="rounded border bg-dark flex-shrink-0" style="width:180px;max-height:110px"><source src="${escapeHtml(url)}"></video>`;
        }
        return '';
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
