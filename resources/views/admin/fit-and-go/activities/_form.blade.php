<div class="row g-4">
    {{-- LEFT COLUMN: Primary Configuration --}}
    <div class="col-12 col-xl-8">
        {{-- Card 1: Activity Identity --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-compass text-primary me-2"></i>Identitas Aktivitas Outdoor</h6>
                <span class="badge bg-light text-dark border">EIGER Activity Persona</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Nama Aktivitas <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $activity->name ?? '') }}" placeholder="Contoh: Mountaineering & Alpine" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Slug Identifier</label>
                        <input type="text" name="slug" class="form-control font-monospace @error('slug') is-invalid @enderror"
                               value="{{ old('slug', $activity->slug ?? '') }}" placeholder="mountaineering (otomatis jika kosong)">
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Kode unik yang digunakan untuk relasi AI & URL.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Deskripsi Aktivitas</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Deskripsi singkat mengenai peruntukan aktivitas, karakteristik medan, dan rekomendasi perlengkapan...">{{ old('description', $activity->description ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: CARE Integration & Media --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-link-45deg text-primary me-2"></i>Integrasi CARE & Media Visual</h6>
                    <small class="text-muted">Pemetaan ke Master Catalog CARE Simulator dan gambar representasi aktivitas.</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">CARE MC Level 2 Category Mapping</label>
                        <input type="text" name="care_mc_level_2" class="form-control @error('care_mc_level_2') is-invalid @enderror"
                               value="{{ old('care_mc_level_2', $activity->care_mc_level_2 ?? '') }}" placeholder="Contoh: Hiking, Riding, Climbing">
                        @error('care_mc_level_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Kategori MC Level 2 pada sistem ERP/CARE EIGER untuk pencocokan otomatis produk.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">URL Gambar Banner / Ikon Aktivitas</label>
                        <input type="url" name="image" id="activity-image-input" class="form-control font-monospace @error('image') is-invalid @enderror"
                               value="{{ old('image', $activity->image ?? '') }}" placeholder="https://...">
                        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Link gambar ilustrasi atau foto lanskap aktivitas petualangan.</div>
                    </div>
                    @if(isset($activity) && $activity->image)
                        <div class="col-12">
                            <div class="p-3 border rounded-3 bg-light d-flex align-items-center gap-3">
                                <img src="{{ $activity->image }}" alt="{{ $activity->name }}"
                                     class="rounded border shadow-sm object-fit-cover" style="width: 120px; height: 68px;">
                                <div>
                                    <div class="fw-semibold text-dark small">Preview Banner Gambar Saat Ini</div>
                                    <div class="text-muted small font-monospace text-truncate" style="max-width: 400px;">{{ $activity->image }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT COLUMN: Priority & Status --}}
    <div class="col-12 col-xl-4">
        {{-- Card 1: Status & Sort Order --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold"><i class="bi bi-sort-numeric-down text-primary me-2"></i>Urutan & Status</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Urutan Prioritas Tampil</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $activity->sort_order ?? 0) }}" min="0">
                    <div class="form-text small">Angka lebih kecil tampil lebih awal pada pilihan antarmuka Kiosk.</div>
                </div>

                <hr class="my-3">

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="act-is-active" name="is_active" value="1"
                           @checked(old('is_active', $activity->is_active ?? true))>
                    <label class="form-check-label fw-semibold" for="act-is-active">Status Aktivitas Aktif</label>
                </div>
                <small class="text-muted d-block">
                    Aktivitas yang aktif akan muncul di layar Kiosk AI Fit & Go dan sebagai pilihan scene pada LED Ambience.
                </small>
            </div>
        </div>

        {{-- Card 2: Help Guide --}}
        <div class="card border-0 shadow-sm rounded-3 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-2"></i>Panduan Aktivitas EIGER</h6>
                <p class="small text-muted mb-2">
                    Aktivitas berfungsi sebagai filter tema utama customer:
                </p>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>AI Fit & Go:</strong> Merekomendasikan outfit yang paling sesuai dengan aktivitas pilihan customer.</li>
                    <li><strong>LED Ambience:</strong> Menyesuaikan video latar, pencahayaan RGB, dan soundscape saat produk aktivitas tersebut diletakkan.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Bottom Action Bar --}}
<div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
    <a href="{{ route('admin.fit-and-go.index', ['tab' => 'activities']) }}" class="btn btn-light px-4">Batal</a>
    <button type="submit" class="btn btn-eiger fw-bold px-4 shadow-sm">
        <i class="bi bi-check-lg me-1"></i>{{ isset($activity) ? 'Simpan Perubahan Aktivitas' : 'Simpan & Tambah Aktivitas' }}
    </button>
</div>
