<div class="row g-4">
    {{-- LEFT COLUMN: Primary Configuration --}}
    <div class="col-12 col-xl-8">
        {{-- Card 1: Device Identity --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-primary me-2"></i>Identitas & Lokasi Kiosk</h6>
                <span class="badge bg-light text-dark border">AI Fit & Go Hardware</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Nama Perangkat Kiosk <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $device->name ?? '') }}" placeholder="Contoh: Kiosk Fitting Room 01" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Kode Perangkat (Device Code) <span class="text-danger">*</span></label>
                        <input type="text" name="device_code" class="form-control font-monospace @error('device_code') is-invalid @enderror"
                               value="{{ old('device_code', $device->device_code ?? '') }}" placeholder="KIOSK-01" required>
                        @error('device_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text small">Kode unik identifikasi unit perangkat di toko.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Lokasi Penempatan Meja / Fitting Room</label>
                        <input type="text" name="location" class="form-control"
                               value="{{ old('location', $device->location ?? '') }}" placeholder="Contoh: Flagship Store Bandung - Lantai 2 Area Pria">
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: AI Workstation & Network --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-cpu text-primary me-2"></i>Workstation GPU & Sumber Kamera</h6>
                    <small class="text-muted">Koneksi inferensi Virtual Try-On dan video input live customer.</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Alamat IP Kiosk (Local Network)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-hdd-network text-muted"></i></span>
                            <input type="text" name="ip_address" class="form-control font-monospace @error('ip_address') is-invalid @enderror"
                                   value="{{ old('ip_address', $device->ip_address ?? '') }}" placeholder="192.168.18.50">
                        </div>
                        @error('ip_address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sumber Kamera (Camera Source / RTSP)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-camera-video text-muted"></i></span>
                            <input type="text" name="camera_source" class="form-control font-monospace"
                                   value="{{ old('camera_source', $device->camera_source ?? '') }}" placeholder="rtsp://... atau /dev/video0">
                        </div>
                        <div class="form-text small">URL stream RTSP atau device node kamera webcam / depth sensor.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">GPU Inference Endpoint URL</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light font-monospace text-muted">HTTP/S</span>
                            <input type="url" name="gpu_endpoint" class="form-control font-monospace @error('gpu_endpoint') is-invalid @enderror"
                                   value="{{ old('gpu_endpoint', $device->gpu_endpoint ?? '') }}" placeholder="http://192.168.18.90:8000/api/v1/virtual-tryon">
                        </div>
                        @error('gpu_endpoint')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text small">Endpoint API AI model inferensi yang memproses visual virtual try-on.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT COLUMN: Status & Operational Info --}}
    <div class="col-12 col-xl-4">
        {{-- Card 1: Operational Status --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold"><i class="bi bi-sliders text-primary me-2"></i>Status & Operasional</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mode Operasional Perangkat <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @php $curStatus = old('status', $device->status ?? 'online'); @endphp
                        <option value="online" @selected($curStatus === 'online')>Online (Siap Digunakan)</option>
                        <option value="active" @selected($curStatus === 'active')>Active (Sedang Interaksi)</option>
                        <option value="maintenance" @selected($curStatus === 'maintenance')>Maintenance (Perawatan)</option>
                        <option value="offline" @selected($curStatus === 'offline')>Offline (Mati / Nonaktif)</option>
                    </select>
                </div>

                <hr class="my-3">

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is-active" name="is_active" value="1"
                           @checked(old('is_active', $device->is_active ?? true))>
                    <label class="form-check-label fw-semibold" for="is-active">Aktifkan Perangkat</label>
                </div>
                <small class="text-muted d-block mb-3">
                    Jika dinonaktifkan, Kiosk ini tidak akan menerima instruksi session atau trigger dari sistem toko.
                </small>

                @isset($device)
                    <hr class="my-3">
                    <h6 class="fw-bold small text-muted text-uppercase mb-2">Telemetri Perangkat</h6>
                    <dl class="row small mb-0 g-1">
                        <dt class="col-6 text-muted">ID Database:</dt>
                        <dd class="col-6 font-monospace fw-bold">#{{ $device->id }}</dd>

                        <dt class="col-6 text-muted">Last Heartbeat:</dt>
                        <dd class="col-6">
                            {{ $device->last_heartbeat_at ? $device->last_heartbeat_at->diffForHumans() : 'Belum pernah' }}
                        </dd>

                        <dt class="col-6 text-muted">Terdaftar Pada:</dt>
                        <dd class="col-6 text-muted">{{ $device->created_at->format('d M Y H:i') }}</dd>
                    </dl>
                @endisset
            </div>
        </div>

        {{-- Card 2: Help Guide --}}
        <div class="card border-0 shadow-sm rounded-3 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-2"></i>Panduan AI Fit & Go Kiosk</h6>
                <p class="small text-muted mb-2">
                    Kiosk AI Fit & Go adalah unit display pintar interaktif di toko untuk visualisasi pakaian dan rekomendasi ukuran otomatis menggunakan AI.
                </p>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Device Code:</strong> Identifier unik untuk pairing aplikasi Kiosk frontend.</li>
                    <li><strong>GPU Endpoint:</strong> Pastikan workstation local TrueNAS/GPU online pada IP yang dapat dijangkau Kiosk.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Bottom Action Bar --}}
<div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
    <a href="{{ route('admin.fit-and-go.index', ['tab' => 'devices']) }}" class="btn btn-light px-4">Batal</a>
    <button type="submit" class="btn btn-eiger fw-bold px-4 shadow-sm">
        <i class="bi bi-check-lg me-1"></i>{{ isset($device) ? 'Simpan Perubahan Perangkat' : 'Simpan & Daftarkan Kiosk' }}
    </button>
</div>
