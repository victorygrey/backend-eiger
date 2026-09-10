@extends('layouts.admin')

@section('title', 'Edit '.$tablet->name)
@section('page-title', 'Edit Tablet')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.tablets.index') }}">Interactive Tablets</a></li>
    <li class="breadcrumb-item active">{{ $tablet->name }}</li>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.tablets.update', $tablet) }}">
    @csrf @method('PUT')
    @include('admin.tablets._form')
</form>

<div class="card mt-4">
    <div class="card-header bg-white py-3"><strong>Riwayat Konfigurasi</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th class="ps-3">Versi</th><th>Featured Product</th><th>Rekomendasi</th><th>Dipublikasikan</th><th class="text-end pe-3">Aksi</th></tr></thead>
                <tbody>
                    @forelse($tablet->versions as $version)
                        <tr>
                            <td class="ps-3 fw-semibold">v{{ $version->version_number }} @if($version->version_number === $tablet->config_version)<span class="badge-soft badge-success-soft ms-2">Aktif</span>@endif</td>
                            <td>{{ $version->featuredProduct?->name ?? 'Produk tidak tersedia' }}</td>
                            <td>{{ count($version->recommendation_product_ids ?? []) }} produk</td>
                            <td>{{ $version->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</td>
                            <td class="text-end pe-3">
                                @if($version->version_number !== $tablet->config_version)
                                    <form method="POST" action="{{ route('admin.tablets.rollback', [$tablet, $version]) }}" onsubmit="return confirm('Pulihkan konfigurasi v{{ $version->version_number }}?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Pulihkan</button>
                                    </form>
                                @else
                                    <span class="text-muted small">Versi sekarang</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
