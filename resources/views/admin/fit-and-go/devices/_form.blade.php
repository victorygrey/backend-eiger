<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-display text-primary me-2"></i>Identitas Perangkat Kiosk</h6>
                <span class="badge bg-light text-dark border">AI Fit &amp; Go</span>
            </div>
            <div class="card-body p-3 p-lg-4">
                <div class="alert alert-light border d-flex align-items-start gap-2 mb-3">
                    <i class="bi bi-lock-fill text-secondary mt-1"></i>
                    <div class="small"><strong>Identitas kiosk dikunci.</strong> Nama, identifier, lokasi, dan status ditetapkan sebagai slot perangkat lantai.</div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold" for="device-name">Nama Perangkat</label>
                        <input id="device-name" type="text" class="form-control bg-light" value="{{ $device->name }}" readonly>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold" for="device-code">Slug URL / Device Identifier</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">/fit-and-go/</span>
                            <input id="device-code" type="text" class="form-control font-monospace bg-light" value="{{ $device->device_code }}" readonly>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="device-location">Lokasi Perangkat</label>
                        <input id="device-location" type="text" class="form-control bg-light" value="{{ $device->location }}" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom"><h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock text-primary me-2"></i>Aktivasi &amp; Keamanan</h6></div>
            <div class="card-body">
                <label class="form-label fw-semibold" for="activation-code">Kode Aktivasi @unless(isset($device))<span class="text-danger">*</span>@endunless</label>
                <div class="input-group">
                    <input id="activation-code" type="text" name="activation_code" class="form-control font-monospace @error('activation_code') is-invalid @enderror"
                           value="{{ old('activation_code', $device->activation_code_encrypted ?? '') }}"
                           minlength="6" maxlength="64" placeholder="Contoh: FLOOR-01" {{ isset($device) ? '' : 'required' }}>
                    <button class="btn btn-outline-secondary" type="button" id="generate-activation-code" title="Buat kode acak"><i class="bi bi-dice-5"></i></button>
                </div>
                @error('activation_code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div class="form-text">Kode disimpan terenkripsi agar tetap dapat dilihat. Mengganti kode akan mencabut token perangkat lama.</div>

                @isset($device)
                    <div class="rounded-3 bg-light border p-3 mt-3 small">
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Kode keamanan</span><strong>{{ $device->activation_code_hash ? 'Tersedia' : 'Belum diatur' }}</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Status pairing</span><strong>{{ $device->device_token_hash ? 'Sudah pairing' : 'Belum pairing' }}</strong></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Heartbeat</span><strong>{{ $device->last_heartbeat_at?->diffForHumans() ?? 'Belum pernah' }}</strong></div>
                    </div>
                @endisset

            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
    <a href="{{ route('admin.fit-and-go.index', ['tab' => 'kiosks']) }}" class="btn btn-light px-4">Batal</a>
    <button type="submit" class="btn btn-eiger fw-bold px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i>Simpan Kode Aktivasi</button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('generate-activation-code')?.addEventListener('click', () => {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = 'FIT-';
        for (let index = 0; index < 8; index++) code += chars[Math.floor(Math.random() * chars.length)];
        document.getElementById('activation-code').value = code;
    });
});
</script>
@endpush
