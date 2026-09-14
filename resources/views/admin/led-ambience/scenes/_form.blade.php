<div class="row g-4">
    {{-- LEFT COLUMN: Primary Configuration --}}
    <div class="col-12 col-xl-8">
        {{-- Card 1: Scene Identity --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-film text-primary me-2"></i>Informasi Scene Ambience</h6>
                <span class="badge bg-light text-dark border">Preset Visual & Audio</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Nama Scene Ambience <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $scene->name ?? '') }}" placeholder="Contoh: Badai Puncak Gunung & Hutan Hujan" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tipe Perilaku Scene <span class="text-danger">*</span></label>
                        @php $curType = old('scene_type', $scene->scene_type ?? 'active'); @endphp
                        <select name="scene_type" class="form-select @error('scene_type') is-invalid @enderror" required>
                            <option value="active" @selected($curType === 'active')>Active Scene (Dipicu saat Produk Terdeteksi)</option>
                            <option value="idle" @selected($curType === 'idle')>Idle Loop (Video Standby Toko)</option>
                            <option value="default" @selected($curType === 'default')>Default Scene (Fallback Umum)</option>
                        </select>
                        @error('scene_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Pilih <em>Idle Loop</em> jika scene ini diputar terus menerus saat toko sedang sepi/standby.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Aktivitas Outdoor Terkait</label>
                        <select name="activity_slug" class="form-select @error('activity_slug') is-invalid @enderror">
                            <option value="">-- Bebas (General / Semua Aktivitas) --</option>
                            @php $curAct = old('activity_slug', $scene->activity_slug ?? ''); @endphp
                            @foreach($activities as $act)
                                <option value="{{ $act->slug }}" @selected($act->slug === $curAct)>
                                    {{ $act->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('activity_slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Scene ini akan otomatis dipicu untuk semua produk dengan aktivitas tersebut jika tidak diatur khusus.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Deskripsi Suasana & Mood</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Deskripsikan suasana visual, tempo musik latar, gemuruh petir, atau efek angin yang diharapkan...">{{ old('description', $scene->description ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Video, Audio, and Lighting --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-soundwave text-primary me-2"></i>Media Konten & Tata Cahaya RGB</h6>
                    <small class="text-muted">Konten video 4K/MP4, audio soundscape stereo/surround, dan kode warna ambient toko.</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">URL Video Layar Lebar (MP4 / WebM / HLS)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-camera-video text-muted"></i></span>
                            <input type="url" name="video_url" class="form-control font-monospace @error('video_url') is-invalid @enderror"
                                   value="{{ old('video_url', $scene->video_url ?? '') }}" placeholder="https://cdn.eigeradventure.com/videos/mountain_storm.mp4">
                        </div>
                        @error('video_url')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text small">Video yang akan dimainkan di videowall / display LED toko.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">URL Audio Suasana Toko (MP3 / WAV / Stream)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-volume-up text-muted"></i></span>
                            <input type="url" name="audio_url" class="form-control font-monospace @error('audio_url') is-invalid @enderror"
                                   value="{{ old('audio_url', $scene->audio_url ?? '') }}" placeholder="https://cdn.eigeradventure.com/audio/mountain_ambient.mp3">
                        </div>
                        @error('audio_url')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text small">Soundscape audio spasial yang diputar otomatis serentak dengan video.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Warna Pencahayaan Ambience (Lighting Color)</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="color" id="lighting_color_picker" name="lighting_color"
                                   class="form-control form-control-color border shadow-sm"
                                   value="{{ old('lighting_color', $scene->lighting_color ?? '#e8500a') }}" title="Pilih warna lampu LED">
                            <input type="text" id="lighting_color_text" class="form-control font-monospace"
                                   value="{{ old('lighting_color', $scene->lighting_color ?? '#e8500a') }}" style="max-width: 140px;" readonly>
                            <span class="small text-muted">
                                Warna ini dikirim ke controller lampu DMX/Smart LED toko saat scene aktif.
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT COLUMN: Status & Operational Info --}}
    <div class="col-12 col-xl-4">
        {{-- Card 1: Priority & Status --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold"><i class="bi bi-sliders text-primary me-2"></i>Prioritas & Status</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Urutan Prioritas Scene</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $scene->sort_order ?? 0) }}" min="0">
                    <div class="form-text small">Prioritas lebih tinggi didahulukan saat multiple trigger terjadi.</div>
                </div>

                <hr class="my-3">

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="scene-is-active" name="is_active" value="1"
                           @checked(old('is_active', $scene->is_active ?? true))>
                    <label class="form-check-label fw-semibold" for="scene-is-active">Scene Aktif</label>
                </div>
                <small class="text-muted d-block mb-3">
                    Jika nonaktif, scene ini tidak akan diputar oleh backend LED Ambience.
                </small>

                @isset($scene)
                    <hr class="my-3">
                    <h6 class="fw-bold small text-muted text-uppercase mb-2">Statistik Scene</h6>
                    <dl class="row small mb-0 g-1">
                        <dt class="col-6 text-muted">ID Database:</dt>
                        <dd class="col-6 font-monospace fw-bold">#{{ $scene->id }}</dd>

                        <dt class="col-6 text-muted">RFID Terhubung:</dt>
                        <dd class="col-6"><span class="badge bg-secondary">{{ $scene->items()->count() }} Tag</span></dd>

                        <dt class="col-6 text-muted">Dibuat Pada:</dt>
                        <dd class="col-6 text-muted">{{ $scene->created_at->format('d M Y H:i') }}</dd>
                    </dl>
                @endisset
            </div>
        </div>

        {{-- Card 2: Help Guide --}}
        <div class="card border-0 shadow-sm rounded-3 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-2"></i>Tipe Scene LED Ambience</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li class="mb-2"><strong>Active:</strong> Dipicu saat pelanggan meletakkan produk ber-RFID tertentu di meja interaktif atau display.</li>
                    <li class="mb-2"><strong>Idle Loop:</strong> Video dan musik santai berulang saat toko tidak mendeteksi interaksi sensor.</li>
                    <li><strong>Default:</strong> Scene cadangan jika produk tidak memiliki scene khusus atau aktivitas yang terdaftar.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Bottom Action Bar --}}
<div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
    <a href="{{ route('admin.led-ambience.index', ['tab' => 'scenes']) }}" class="btn btn-light px-4">Batal</a>
    <button type="submit" class="btn btn-eiger fw-bold px-4 shadow-sm">
        <i class="bi bi-check-lg me-1"></i>{{ isset($scene) ? 'Simpan Perubahan Scene' : 'Simpan & Tambah Scene' }}
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const picker = document.getElementById('lighting_color_picker');
    const textInput = document.getElementById('lighting_color_text');
    if (picker && textInput) {
        picker.addEventListener('input', function () {
            textInput.value = this.value;
        });
    }
});
</script>
@endpush
