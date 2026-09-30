@extends('layouts.admin')

@section('title', 'LED Ambience')
@section('page-title', 'LED Ambience')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Konfigurasi</li>
    <li class="breadcrumb-item active">LED Ambience</li>
@endsection

@push('styles')
<style>
    .ambience-table { min-width: 1040px; }
    .ambience-table thead th { background: #171b2d; border: 0; color: #ff6a00; font-size: .72rem; letter-spacing: .06em; padding: 1rem; text-transform: uppercase; white-space: nowrap; }
    .ambience-table tbody td { border-color: #eceff3; padding: 1rem; vertical-align: middle; }
    .ambience-table tbody tr:hover { background: rgba(255, 106, 0, .04); }
    .template-icon { align-items: center; border-radius: 12px; color: #fff; display: inline-flex; flex: 0 0 auto; height: 44px; justify-content: center; width: 44px; }
    .media-preview { background: #f8f9fb; border: 1px solid #e5e7eb; border-radius: 10px; min-width: 220px; overflow: hidden; padding: .55rem; }
    .media-preview video { background: #111827; border-radius: 7px; height: 90px; object-fit: cover; width: 180px; }
    .media-preview audio { height: 34px; max-width: 230px; width: 100%; }
    .empty-media { align-items: center; color: #8b93a1; display: flex; font-size: .76rem; gap: .45rem; min-height: 42px; }
    .ambience-flow { background: linear-gradient(135deg, rgba(255,106,0,.1), rgba(23,27,45,.04)); border: 1px solid rgba(255,106,0,.18); }
</style>
@endpush

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-soundwave text-warning me-2"></i>LED Ambience</h4>
        <p class="text-muted small mb-0">Satu konfigurasi video dan suara global untuk setiap kelompok activity Digital Store.</p>
    </div>
    <a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-dark flex-shrink-0">
        <i class="bi bi-broadcast-pin me-1"></i>Atur RFID LED Ambience
    </a>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['label' => 'Template Global', 'value' => $summary['templates'], 'icon' => 'bi-grid-fill', 'color' => '#334155'],
        ['label' => 'Video Terpasang', 'value' => $summary['videos'], 'icon' => 'bi-camera-video-fill', 'color' => '#2563eb'],
        ['label' => 'Audio Terpasang', 'value' => $summary['audio'], 'icon' => 'bi-volume-up-fill', 'color' => '#7c3aed'],
        ['label' => 'RFID LED Aktif', 'value' => $summary['rfid'], 'icon' => 'bi-broadcast-pin', 'color' => '#15803d'],
    ] as $item)
        <div class="col-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body d-flex align-items-center gap-3 py-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:42px;height:42px;background:{{ $item['color'] }}"><i class="bi {{ $item['icon'] }} fs-5"></i></span>
                <div><div class="text-muted small">{{ $item['label'] }}</div><div class="fs-4 fw-bold lh-1">{{ $item['value'] }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="ambience-flow rounded-3 p-3 mb-4 small">
    <div class="fw-bold mb-1"><i class="bi bi-diagram-3-fill text-warning me-1"></i>Alur otomatis</div>
    <div class="text-muted">RFID aktif → produk terhubung → activity paling dominan → salah satu dari empat kelompok utama → video dan audio template global. Produk tanpa activity yang dikenali memakai template Idle / Standby.</div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 px-3 px-lg-4">
        <h6 class="mb-1 fw-bold">Konfigurasi Konten Global</h6>
        <small class="text-muted">Template bersifat tetap dan berlaku untuk satu perangkat LED Ambience.</small>
    </div>
    <div class="table-responsive">
        <table class="table ambience-table align-middle mb-0">
            <thead><tr>
                <th class="ps-4">Activity / Template</th>
                <th>Video</th>
                <th>Sound</th>
                <th>Status</th>
                <th class="pe-4 text-end">Action</th>
            </tr></thead>
            <tbody>
                @foreach($templates as $template)
                    @php
                        $videoUrl = \App\Support\PimMediaUrl::toPublicUrl($template->video_url);
                        $audioUrl = \App\Support\PimMediaUrl::toPublicUrl($template->audio_url);
                        $colors = ['idle' => '#334155', 'mountaineering' => '#2563eb', 'lifestyle' => '#16a34a', 'tactical' => '#64748b', 'riding' => '#dc2626'];
                        $icons = ['idle' => 'bi-moon-stars-fill', 'mountaineering' => 'bi-triangle-fill', 'lifestyle' => 'bi-sun-fill', 'tactical' => 'bi-crosshair', 'riding' => 'bi-bicycle'];
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <span class="template-icon" style="background:{{ $colors[$template->template_key] ?? '#334155' }}"><i class="bi {{ $icons[$template->template_key] ?? 'bi-grid-fill' }}"></i></span>
                                <div>
                                    <div class="fw-bold text-dark">{{ $template->name }}</div>
                                    <code class="small">{{ $template->template_key }}</code>
                                    @if($template->template_key === 'idle')<div class="text-muted small mt-1">Fallback saat standby atau activity tidak dikenali</div>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="media-preview">
                                @if($videoUrl)
                                    <video controls preload="metadata"><source src="{{ $videoUrl }}">Browser tidak mendukung video.</video>
                                    <a href="{{ $videoUrl }}" target="_blank" class="small text-decoration-none d-block mt-1 text-truncate"><i class="bi bi-box-arrow-up-right me-1"></i>Buka video</a>
                                @else
                                    <div class="empty-media"><i class="bi bi-camera-video fs-5"></i>Belum ada video</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="media-preview">
                                @if($audioUrl)
                                    <audio controls preload="metadata"><source src="{{ $audioUrl }}">Browser tidak mendukung audio.</audio>
                                    <a href="{{ $audioUrl }}" target="_blank" class="small text-decoration-none d-block mt-1 text-truncate"><i class="bi bi-box-arrow-up-right me-1"></i>Buka audio</a>
                                @else
                                    <div class="empty-media"><i class="bi bi-volume-mute fs-5"></i>Belum ada audio</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($template->is_active)
                                <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                            @else
                                <span class="badge rounded-pill text-bg-secondary">Not Active</span>
                            @endif
                        </td>
                        <td class="pe-4 text-end">
                            <a href="{{ route('admin.led-ambience.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill me-1"></i>Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
