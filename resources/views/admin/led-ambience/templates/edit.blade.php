@extends('layouts.admin')

@section('title', 'Edit Template '.$template->name)
@section('page-title', 'Edit Template LED Ambience')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.led-ambience.index') }}" class="text-decoration-none">LED Ambience</a></li>
    <li class="breadcrumb-item active">{{ $template->name }}</li>
@endsection

@push('styles')
<style>
    .current-media { background: #f8f9fb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem; }
    .current-media video { background: #111827; border-radius: 9px; max-height: 260px; width: 100%; }
    .current-media audio { width: 100%; }
</style>
@endpush

@section('content')
@php
    $videoUrl = \App\Support\PimMediaUrl::toPublicUrl($template->video_url);
    $audioUrl = \App\Support\PimMediaUrl::toPublicUrl($template->audio_url);
@endphp
<div class="mb-3"><a href="{{ route('admin.led-ambience.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a></div>

<form action="{{ route('admin.led-ambience.templates.update', $template) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-grid-fill text-primary me-2"></i>Template {{ $template->name }}</h6></div>
                <div class="card-body">
                    <div class="alert alert-light border small"><strong>Template tetap:</strong> <code>{{ $template->template_key }}</code>. Nama dan kelompoknya dikunci agar API perangkat selalu konsisten.</div>
                    <label class="form-label fw-semibold">Deskripsi Suasana</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" placeholder="Jelaskan mood, visual, dan karakter suara ambience...">{{ old('description', $template->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-camera-video-fill text-primary me-2"></i>Video Ambience</h6></div>
                <div class="card-body">
                    @if($videoUrl)
                        <div class="current-media mb-3"><video controls preload="metadata"><source src="{{ $videoUrl }}"></video><a href="{{ $videoUrl }}" target="_blank" class="small d-block mt-2">Buka file video saat ini</a></div>
                    @endif
                    <label class="form-label fw-semibold">{{ $videoUrl ? 'Ganti Video' : 'Upload Video' }}</label>
                    <input type="file" name="video_file" class="form-control @error('video_file') is-invalid @enderror" accept="video/mp4,video/webm">
                    @error('video_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">MP4 atau WebM, maksimal {{ config('led_ambience.max_video_mb') }} MB. File disimpan ke folder NAS khusus template ini.</div>
                    @if($videoUrl)<div class="form-check mt-3"><input type="hidden" name="remove_video" value="0"><input class="form-check-input" type="checkbox" name="remove_video" value="1" id="remove-video"><label class="form-check-label text-danger" for="remove-video">Lepaskan video dari template saat disimpan</label></div>@endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-volume-up-fill text-warning me-2"></i>Sound Ambience</h6></div>
                <div class="card-body">
                    @if($audioUrl)
                        <div class="current-media mb-3"><audio controls preload="metadata"><source src="{{ $audioUrl }}"></audio><a href="{{ $audioUrl }}" target="_blank" class="small d-block mt-2">Buka file audio saat ini</a></div>
                    @endif
                    <label class="form-label fw-semibold">{{ $audioUrl ? 'Ganti Audio' : 'Upload Audio' }}</label>
                    <input type="file" name="audio_file" class="form-control @error('audio_file') is-invalid @enderror" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/aac">
                    @error('audio_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">MP3, WAV, OGG, M4A, atau AAC, maksimal {{ config('led_ambience.max_audio_mb') }} MB.</div>
                    @if($audioUrl)<div class="form-check mt-3"><input type="hidden" name="remove_audio" value="0"><input class="form-check-input" type="checkbox" name="remove_audio" value="1" id="remove-audio"><label class="form-check-label text-danger" for="remove-audio">Lepaskan audio dari template saat disimpan</label></div>@endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-sliders text-primary me-2"></i>Status & Cahaya</h6></div>
                <div class="card-body">
                    <label class="form-label fw-semibold">Warna Pencahayaan</label>
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <input type="color" id="lighting-color" name="lighting_color" class="form-control form-control-color" value="{{ old('lighting_color', $template->lighting_color) }}">
                        <code id="lighting-value">{{ old('lighting_color', $template->lighting_color) }}</code>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="template-active" @checked(old('is_active', $template->is_active))>
                        <label class="form-check-label fw-semibold" for="template-active">Template Aktif</label>
                    </div>
                    <small class="text-muted d-block mt-2">Jika template kelompok nonaktif, API akan memakai Idle / Standby sebagai fallback.</small>
                </div>
            </div>
            <div class="card border-0 bg-light"><div class="card-body small text-muted"><i class="bi bi-hdd-network-fill text-warning me-2"></i>Media disimpan di <code>eiger-media/led-ambience</code> pada NAS berdasarkan jenis media dan template.</div></div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top"><a href="{{ route('admin.led-ambience.index') }}" class="btn btn-light">Batal</a><button class="btn btn-eiger fw-bold"><i class="bi bi-check-lg me-1"></i>Simpan Template</button></div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.getElementById('lighting-color');
    const value = document.getElementById('lighting-value');
    picker?.addEventListener('input', () => { value.textContent = picker.value; });
});
</script>
@endpush
