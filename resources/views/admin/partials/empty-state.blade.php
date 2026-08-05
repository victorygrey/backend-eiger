{{-- Empty state partial --}}
<div class="empty-state">
    <div class="empty-icon"><i class="bi {{ $icon ?? 'bi-inbox' }}"></i></div>
    <div class="empty-title">{{ $title ?? 'Tidak ada data' }}</div>
    <div class="empty-sub">{{ $sub ?? 'Data belum tersedia saat ini.' }}</div>
    @if(!empty($actionUrl) && !empty($actionLabel))
        <a href="{{ $actionUrl }}" class="btn btn-eiger btn-sm mt-3">
            <i class="bi bi-plus-lg me-1"></i>{{ $actionLabel }}
        </a>
    @endif
</div>
