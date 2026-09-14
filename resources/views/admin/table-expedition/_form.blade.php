@php
    $currentTag = old('rfid_tag', $item->rfid_tag ?? '');
    $selectedProdId = (int) old('product_id', $item->product_id ?? 0);
    $selectedAct = old('activity_slug', $item->activity_slug ?? '');

    $featuresVal = old('features');
    if ($featuresVal === null) {
        $featuresVal = isset($item) && is_array($item->features) ? implode("\n", $item->features) : '';
    }

    $techVal = old('technical_details');
    if ($techVal === null) {
        $techStr = '';
        if (isset($item) && is_array($item->technical_details)) {
            foreach ($item->technical_details as $k => $v) {
                $techStr .= is_string($k) ? "{$k}: {$v}\n" : "{$v}\n";
            }
        }
        $techVal = trim($techStr);
    }

    $selSimilar = old('similar_product_ids', isset($item) ? ($item->similar_product_ids ?? []) : []);
    if (!is_array($selSimilar)) {
        $selSimilar = [];
    }
    $selSimilar = array_map('intval', $selSimilar);
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
                                    <option value="{{ $rt->uid }}" data-product-id="{{ $rt->product_id ?? '' }}" @selected($rt->uid === $currentTag)>
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

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Produk EIGER Terkait <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Produk EIGER --</option>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}" @selected($prod->id === $selectedProdId)>
                                    {{ $prod->name }} ({{ $prod->sku }}) - {{ $prod->zone?->name ?? 'Outdoor' }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

        {{-- Card 2: Multimedia & Technical Details --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-cpu text-warning me-2"></i>Multimedia, Fitur & Spesifikasi Teknis</h6>
                    <small class="text-muted">Konten detail yang ditampilkan pada layar Table Expedition saat produk diletakkan di atas meja.</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">URL Video Demo Produk (MP4 / WebM / Streaming URL)</label>
                        <input type="url" name="video_url" class="form-control font-monospace @error('video_url') is-invalid @enderror"
                               value="{{ old('video_url', $item->video_url ?? '') }}" placeholder="https://cdn.eigeradventure.com/videos/expedition-jacket.mp4">
                        @error('video_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Video yang diputar otomatis pada frame demonstrasi produk saat tag RFID terbaca.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Fitur Utama</label>
                        <textarea name="features" class="form-control font-monospace small @error('features') is-invalid @enderror" rows="5"
                                  placeholder="Teknologi Tropic Waterproof&#10;Resleting tahan air YKK&#10;Ventilasi ketiak dengan resleting&#10;Tudung kepala dapat diatur">{{ $featuresVal }}</textarea>
                        @error('features')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Tuliskan 1 poin fitur per baris (tekan Enter untuk baris baru).</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Spesifikasi Teknis (Format: Label: Nilai)</label>
                        <textarea name="technical_details" class="form-control font-monospace small @error('technical_details') is-invalid @enderror" rows="5"
                                  placeholder="Material: 3-Layer GORE-TEX&#10;Berat: 480 gram&#10;Waterproof Rating: 28.000 mm&#10;Breathability: RET < 9&#10;Garansi: 1 Tahun">{{ $techVal }}</textarea>
                        @error('technical_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Gunakan tanda titik dua (<code>Label: Nilai</code>) di setiap baris.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Ringkasan AI (AI Summary Product Knowledge)</label>
                        <textarea name="ai_summary" class="form-control @error('ai_summary') is-invalid @enderror" rows="3"
                                  placeholder="Jaket ekspedisi teknis dirancang khusus untuk kondisi cuaca ekstrem di pegunungan tinggi dengan perlindungan maksimal dari angin kencang dan badai salju.">{{ old('ai_summary', $item->ai_summary ?? '') }}</textarea>
                        @error('ai_summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Ringkasan penjelasan produk yang dibacakan atau disajikan oleh asisten AI kepada pengunjung toko.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Similar Products for Recommendation & Comparison --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shuffle text-success me-2"></i>Produk Rekomendasi & Komparasi Serupa</h6>
                    <small class="text-muted">Pilih hingga maksimal 5 produk untuk ditampilkan sebagai opsi perbandingan di layar meja.</small>
                </div>
                <span class="badge bg-secondary-subtle text-secondary" id="selected-sim-count">{{ count($selSimilar) }} / 5 Dipilih</span>
            </div>
            <div class="card-body">
                <select name="similar_product_ids[]" id="similar_products_select" class="form-select @error('similar_product_ids') is-invalid @enderror" multiple size="6">
                    @foreach($products as $simProd)
                        <option value="{{ $simProd->id }}" @selected(in_array($simProd->id, $selSimilar))>
                            {{ $simProd->name }} ({{ $simProd->sku }}) — Rp {{ number_format($simProd->price ?? 0, 0, ',', '.') }} [{{ $simProd->zone?->name ?? 'Outdoor' }}]
                        </option>
                    @endforeach
                </select>
                @error('similar_product_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text small mt-2">
                    Tahan tombol <strong>Ctrl</strong> (Windows) atau <strong>Command</strong> (Mac) untuk memilih beberapa produk (maksimal 5).
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
    // 1. Auto-select product when RFID tag is picked from master dropdown
    const selectEl = document.querySelector('.rfid-select-field');
    if (selectEl) {
        selectEl.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            const productId = selectedOpt ? selectedOpt.getAttribute('data-product-id') : null;
            if (productId) {
                const form = this.closest('form');
                if (form) {
                    const prodSelect = form.querySelector('select[name="product_id"]');
                    if (prodSelect) prodSelect.value = productId;
                }
            }
        });
    }

    // 2. Toggle manual RFID input vs master select
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

    // 3. Similar products counter
    const simSelect = document.getElementById('similar_products_select');
    const simCountBadge = document.getElementById('selected-sim-count');
    if (simSelect && simCountBadge) {
        simSelect.addEventListener('change', function () {
            const count = Array.from(this.selectedOptions).length;
            simCountBadge.textContent = `${count} / 5 Dipilih`;
            if (count > 5) {
                simCountBadge.classList.remove('bg-secondary-subtle', 'text-secondary');
                simCountBadge.classList.add('bg-danger', 'text-white');
            } else {
                simCountBadge.classList.remove('bg-danger', 'text-white');
                simCountBadge.classList.add('bg-secondary-subtle', 'text-secondary');
            }
        });
    }
});
</script>
@endpush
