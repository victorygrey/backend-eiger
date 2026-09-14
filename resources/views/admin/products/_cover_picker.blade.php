<div class="col-12" id="cover-photo-picker-component">
    <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-1">
        <span>
            <i class="bi bi-images text-primary me-1"></i>Foto Cover Produk & Galeri PIM
        </span>
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" id="pim-photo-counter">
            {{ !empty($availableImages) ? count($availableImages) . ' Foto Tersedia' : 'Belum ada foto PIM' }}
        </span>
    </label>
    <div class="card border rounded-3 bg-light bg-opacity-50 p-3">
        <div class="row g-3 align-items-start">
            <!-- Pratinjau Foto Cover Aktif -->
            <div class="col-auto text-center">
                <div class="position-relative d-inline-block">
                    @php
                        $currentCover = old('image', isset($product) ? $product->image : '');
                    @endphp
                    <img id="cover-photo-preview"
                         src="{{ $currentCover ?: 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22120%22%20height%3D%22120%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20120%20120%22%20preserveAspectRatio%3D%22none%22%3E%3Crect%20width%3D%22120%22%20height%3D%22120%22%20fill%3D%22%23f3f4f6%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%239ca3af%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%2211%22%3ENo%20Cover%3C%2Ftext%3E%3C%2Fsvg%3E' }}"
                         alt="Cover Preview"
                         class="rounded-3 border border-2 shadow-sm bg-white"
                         style="width: 110px; height: 110px; object-fit: contain; padding: 3px;"
                         onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22120%22%20height%3D%22120%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20120%20120%22%3E%3Crect%20width%3D%22120%22%20height%3D%22120%22%20fill%3D%22%23fee2e2%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%23ef4444%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%2210%22%3EError%3C%2Ftext%3E%3C%2Fsvg%3E';">
                    <span class="position-absolute bottom-0 start-50 translate-middle-x badge bg-primary shadow-sm mb-1 px-2 py-1 small">
                        <i class="bi bi-star-fill text-warning me-1"></i>Cover
                    </span>
                </div>
            </div>

            <!-- Galeri Foto PIM dan Pemilihan Cover -->
            <div class="col">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted">
                        <i class="bi bi-hand-index-thumb me-1"></i>Klik salah satu foto di bawah ini untuk menjadikannya <strong>Photo Cover</strong> utama:
                    </span>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 text-muted small" id="btn-toggle-manual-cover"
                            data-bs-toggle="collapse" data-bs-target="#manual-cover-url-group">
                        <i class="bi bi-pencil-square me-1"></i>Edit URL Manual
                    </button>
                </div>

                <!-- Container Galeri Foto PIM -->
                <div id="pim-photo-selector" class="d-flex flex-wrap gap-2 p-2 border rounded-3 bg-white shadow-sm"
                     style="max-height: 180px; overflow-y: auto;">
                    @php
                        $imagesList = !empty($availableImages) ? $availableImages : ($currentCover ? [$currentCover] : []);
                    @endphp
                    @forelse($imagesList as $idx => $imgUrl)
                        @php $isActive = ($imgUrl === $currentCover) || (empty($currentCover) && $idx === 0); @endphp
                        <div class="pim-photo-card position-relative rounded border {{ $isActive ? 'border-primary border-3 shadow-sm active-cover' : 'border-secondary border-opacity-25' }} p-1 bg-white cursor-pointer"
                             data-url="{{ $imgUrl }}"
                             style="cursor: pointer; width: 72px; height: 72px; transition: all 0.2s;"
                             title="Klik untuk jadikan Cover">
                            <img src="{{ $imgUrl }}" alt="PIM Photo {{ $idx + 1 }}"
                                 class="w-100 h-100 rounded"
                                 style="object-fit: contain;"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2260%22%20height%3D%2260%22%20fill%3D%22%23f3f4f6%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%239ca3af%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%229%22%3EImg%3C%2Ftext%3E%3C%2Fsvg%3E';">
                            <span class="cover-badge position-absolute top-0 end-0 badge bg-primary p-1 {{ $isActive ? '' : 'd-none' }}"
                                  style="font-size: 8px; border-top-right-radius: 3px;">
                                <i class="bi bi-check-lg"></i>
                            </span>
                        </div>
                    @empty
                        <div class="w-100 text-center py-3 text-muted small" id="pim-photo-empty-msg">
                            <i class="bi bi-cloud-arrow-down fs-4 d-block mb-1 text-primary text-opacity-50"></i>
                            Belum ada galeri foto. Masukkan Kode Artikel dan klik <strong>"Tarik Data PIM & CARE"</strong> di atas untuk memuat seluruh foto dari PIM TrueNAS.
                        </div>
                    @endforelse
                </div>

                <!-- Input URL Manual (Bisa dibuka dengan tombol Edit URL Manual) -->
                <div class="collapse mt-2" id="manual-cover-url-group">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted small"><i class="bi bi-link-45deg me-1"></i>URL Cover</span>
                        <input type="text" name="image" id="input-cover-image" value="{{ $currentCover }}"
                               class="form-control form-control-sm font-monospace @error('image') is-invalid @enderror"
                               placeholder="https://... atau http://192.168.18.31:8001/media/...">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-apply-manual-cover">Terapkan</button>
                    </div>
                </div>
                <!-- Input hidden aktif jika collapse tertutup agar form tetap tersubmit -->
                <input type="hidden" name="image" id="hidden-cover-image" value="{{ $currentCover }}">
                @error('image') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const previewImg = document.getElementById('cover-photo-preview');
    const inputManual = document.getElementById('input-cover-image');
    const inputHidden = document.getElementById('hidden-cover-image');
    const selectorContainer = document.getElementById('pim-photo-selector');
    const counterBadge = document.getElementById('pim-photo-counter');

    // Inisialisasi daftar foto global yang tersedia
    window.availablePimPhotos = @json(!empty($availableImages) ? $availableImages : ($currentCover ? [$currentCover] : []));

    // Sinkronisasi nilai cover saat memilih thumbnail
    function setCoverPhoto(url) {
        if (!url) return;
        if (inputManual) inputManual.value = url;
        if (inputHidden) inputHidden.value = url;
        if (previewImg) previewImg.src = url;

        // Update styling kartu foto aktif
        document.querySelectorAll('.pim-photo-card').forEach(card => {
            const isMatch = card.dataset.url === url;
            card.classList.toggle('border-primary', isMatch);
            card.classList.toggle('border-3', isMatch);
            card.classList.toggle('shadow-sm', isMatch);
            card.classList.toggle('active-cover', isMatch);
            card.classList.toggle('border-secondary', !isMatch);
            card.classList.toggle('border-opacity-25', !isMatch);
            const badge = card.querySelector('.cover-badge');
            if (badge) badge.classList.toggle('d-none', !isMatch);
        });
    }

    // Event listener delegasi klik pada galeri foto
    if (selectorContainer) {
        selectorContainer.addEventListener('click', (e) => {
            const card = e.target.closest('.pim-photo-card');
            if (card && card.dataset.url) {
                setCoverPhoto(card.dataset.url);
            }
        });
    }

    // Terapkan URL manual jika user mengetik
    const btnApplyManual = document.getElementById('btn-apply-manual-cover');
    if (btnApplyManual && inputManual) {
        btnApplyManual.addEventListener('click', () => {
            const url = inputManual.value.trim();
            if (url) {
                setCoverPhoto(url);
                if (!window.availablePimPhotos.includes(url)) {
                    window.availablePimPhotos.unshift(url);
                    renderPhotoCards(window.availablePimPhotos, url);
                }
            }
        });
        inputManual.addEventListener('change', () => {
            const url = inputManual.value.trim();
            if (url) setCoverPhoto(url);
        });
    }

    // Fungsi render ulang galeri foto saat data PIM ditarik
    function renderPhotoCards(photos, selectedUrl) {
        if (!Array.isArray(photos) || photos.length === 0 || !selectorContainer) return;
        window.availablePimPhotos = photos;
        selectorContainer.replaceChildren();

        const activeUrl = selectedUrl || photos[0];
        photos.forEach((url, i) => {
            const isActive = url === activeUrl;
            const card = document.createElement('div');
            card.className = `pim-photo-card position-relative rounded border ${isActive ? 'border-primary border-3 shadow-sm active-cover' : 'border-secondary border-opacity-25'} p-1 bg-white cursor-pointer`;
            card.dataset.url = url;
            card.style.cssText = 'cursor: pointer; width: 72px; height: 72px; transition: all 0.2s;';
            card.title = `Klik untuk jadikan Cover (Foto ${i + 1})`;
            card.innerHTML = `
                <img src="${url}" alt="Foto ${i + 1}" class="w-100 h-100 rounded" style="object-fit: contain;" loading="lazy"
                     onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2260%22%20height%3D%2260%22%20fill%3D%22%23f3f4f6%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%239ca3af%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%229%22%3EImg%3C%2Ftext%3E%3C%2Fsvg%3E';">
                <span class="cover-badge position-absolute top-0 end-0 badge bg-primary p-1 ${isActive ? '' : 'd-none'}" style="font-size: 8px; border-top-right-radius: 3px;">
                    <i class="bi bi-check-lg"></i>
                </span>
            `;
            selectorContainer.appendChild(card);
        });

        if (counterBadge) {
            counterBadge.textContent = `${photos.length} Foto PIM Tersedia`;
        }

        setCoverPhoto(activeUrl);

        // Notifikasi ke komponen varian agar modal pemilihan foto varian juga ter-update
        if (typeof window.refreshVariantPhotoPicker === 'function') {
            window.refreshVariantPhotoPicker();
        }
    }

    // Expose ke window untuk dipanggil oleh _pim.blade.php
    window.updateAvailablePimPhotos = function(photos, selectedUrl) {
        renderPhotoCards(photos, selectedUrl);
    };
});
</script>
