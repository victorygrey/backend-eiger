@extends('layouts.admin')

@section('title', 'Interactive Tablets')
@section('page-title', 'Interactive Tablets')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Interactive Tablets</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-tablet-landscape-fill text-primary me-2"></i>Konfigurasi Tablet</h4>
        <p class="text-muted small mb-0">Set featured product dan rekomendasi berbeda untuk setiap perangkat.</p>
    </div>
    <a href="{{ route('admin.tablets.create') }}" class="btn btn-eiger">
        <i class="bi bi-plus-lg me-1"></i>Tambah Tablet
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Tablet</th>
                        <th>Lokasi</th>
                        <th>Featured Product</th>
                        <th>Rekomendasi</th>
                        <th>Versi</th>
                        <th>Status Perangkat</th>
                        <th class="pe-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tablets as $tablet)
                        @php($online = $tablet->last_seen_at?->gt(now()->subMinutes(2)) ?? false)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $tablet->name }}</div>
                                <small class="text-muted">/tablet/{{ $tablet->slug }}</small>
                            </td>
                            <td>{{ $tablet->location ?: '—' }}</td>
                            <td>
                                @if($tablet->featuredProduct)
                                    <div class="fw-semibold">{{ $tablet->featuredProduct->name }}</div>
                                    <small class="text-muted">{{ $tablet->featuredProduct->sku }}</small>
                                @else
                                    <span class="badge-soft badge-warning-soft">Belum diatur</span>
                                @endif
                            </td>
                            <td>{{ $tablet->recommendations_count }} produk</td>
                            <td>v{{ $tablet->config_version }}</td>
                            <td>
                                @if(!$tablet->is_active)
                                    <span class="badge-soft badge-gray-soft"><i class="bi bi-pause-circle"></i>Nonaktif</span>
                                @elseif($online)
                                    <span class="badge-soft badge-success-soft"><i class="bi bi-wifi"></i>Online</span>
                                @else
                                    <span class="badge-soft badge-warning-soft"><i class="bi bi-wifi-off"></i>Offline</span>
                                @endif
                                @if($tablet->last_seen_at)
                                    <div><small class="text-muted">{{ $tablet->last_seen_at->diffForHumans() }}</small></div>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group-actions">
                                    <a href="{{ route('admin.tablets.edit', $tablet) }}" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form action="{{ route('admin.tablets.destroy', $tablet) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus konfigurasi {{ $tablet->name }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">
                            @include('admin.partials.empty-state', [
                                'icon' => 'bi-tablet-landscape',
                                'title' => 'Belum ada tablet',
                                'sub' => 'Tambahkan perangkat pertama dan pilih produk yang akan ditampilkan.',
                                'actionUrl' => route('admin.tablets.create'),
                                'actionLabel' => 'Tambah Tablet',
                            ])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', ['paginator' => $tablets, 'resource' => 'tablet'])
    </div>
</div>
@endsection
