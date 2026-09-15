<div class="row g-4">
    {{-- LEFT COLUMN: Primary Configuration --}}
    <div class="col-12 col-xl-8">
        {{-- Card 1: RFID & Product --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-broadcast-pin text-primary me-2"></i>Identifikasi Tag RFID & Produk EIGER</h6>
                <span class="badge bg-light text-dark border">LED Ambience Trigger</span>
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
                                @php $currentTag = old('rfid_tag', $item->rfid_tag ?? ''); @endphp
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

                    <div class="col-12">
                        <label class="form-label fw-semibold">Catatan Penempatan / Keterangan</label>
                        <input type="text" name="notes" class="form-control"
                               value="{{ old('notes', $item->notes ?? '') }}" placeholder="Contoh: Jaket sample display rak depan sebelah kiri">
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Ambience Scene & Activity Trigger --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-film text-warning me-2"></i>Pemicu Suasana Ambience & Aktivitas</h6>
                    <small class="text-muted">Tentukan scene ambience atau biarkan otomatis memilih berdasarkan aktivitas produk.</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Aktivitas Outdoor (EIGER Activity)</label>
                        <select name="activity_slug" class="form-select @error('activity_slug') is-invalid @enderror">
                            <option value="">-- Bebas (General / Ikuti Produk) --</option>
                            @php $selectedAct = old('activity_slug', $item->activity_slug ?? ''); @endphp
                            @foreach($activities as $act)
                                <option value="{{ $act->slug }}" @selected($act->slug === $selectedAct)>
                                    {{ $act->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('activity_slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Aktivitas yang dipicu saat produk diletakkan di rak/display LED Ambience.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Scene Ambience Spesifik</label>
                        <select name="scene_id" class="form-select @error('scene_id') is-invalid @enderror">
                            <option value="">-- Otomatis Sesuai Aktivitas --</option>
                            @php $selectedScene = (int) old('scene_id', $item->scene_id ?? 0); @endphp
                            @foreach($scenes as $sc)
                                <option value="{{ $sc->id }}" @selected($sc->id === $selectedScene)>
                                    {{ $sc->name }} ({{ strtoupper($sc->scene_type) }})
                                </option>
                            @endforeach
                        </select>
                        @error('scene_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Pilih jika ingin memicu video & audio tertentu di luar preset aktivitas.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT COLUMN: Status & Operational Info --}}
    <div class="col-12 col-xl-4">
        {{-- Card 1: Status --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold"><i class="bi bi-sliders text-primary me-2"></i>Status & Operasional</h6>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="rfid-is-active" name="is_active" value="1"
                           @checked(old('is_active', $item->is_active ?? true))>
                    <label class="form-check-label fw-semibold" for="rfid-is-active">Pemetaan RFID Aktif</label>
                </div>
                <small class="text-muted d-block mb-3">
                    Jika dinonaktifkan, scanner RFID tidak akan mengubah scene ambience toko saat mendeteksi tag ini.
                </small>

                @isset($item)
                    <hr class="my-3">
                    <h6 class="fw-bold small text-muted text-uppercase mb-2">Riwayat Deteksi</h6>
                    <dl class="row small mb-0 g-1">
                        <dt class="col-6 text-muted">ID Database:</dt>
                        <dd class="col-6 font-monospace fw-bold">#{{ $item->id }}</dd>

                        <dt class="col-6 text-muted">Terakhir Di-scan:</dt>
                        <dd class="col-6">{{ $item->last_scanned_at ? $item->last_scanned_at->diffForHumans() : 'Belum pernah' }}</dd>

                        <dt class="col-6 text-muted">Waktu Dibuat:</dt>
                        <dd class="col-6 text-muted">{{ $item->created_at->format('d M Y H:i') }}</dd>
                    </dl>
                @endisset
            </div>
        </div>

        {{-- Card 2: Help Guide --}}
        <div class="card border-0 shadow-sm rounded-3 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-2"></i>Panduan LED Ambience</h6>
                <p class="small text-muted mb-2">
                    Sistem <strong>LED Ambience Digital</strong> mendeteksi tag RFID saat produk diangkat atau diletakkan:
                </p>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Standby:</strong> Menjalankan <em>Idle Loop</em> video dan soundscape alam.</li>
                    <li><strong>Triggered:</strong> Begitu RFID terbaca, layar LED dinding toko dan pencahayaan otomatis berubah ke scene petualangan produk terkait.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Bottom Action Bar --}}
<div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
    <a href="{{ route('admin.led-ambience.index', ['tab' => 'rfid']) }}" class="btn btn-light px-4">Batal</a>
    <button type="submit" class="btn btn-eiger fw-bold px-4 shadow-sm">
        <i class="bi bi-check-lg me-1"></i>{{ isset($item) ? 'Simpan Perubahan Mapping' : 'Simpan Mapping RFID' }}
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
});
</script>
@endpush
