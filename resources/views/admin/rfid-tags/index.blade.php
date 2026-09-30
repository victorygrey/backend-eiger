@extends('layouts.admin')

@section('title', 'Master RFID Tags')
@section('page-title', 'Master RFID Tags')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Manajemen Data</a></li>
    <li class="breadcrumb-item active">RFID Tags</li>
@endsection

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-broadcast-pin text-warning me-2"></i>Master RFID Tags</h4>
        <p class="text-muted small mb-0">Hubungkan UID/EPC fisik ke SKU utama. Pemetaan ini dipakai bersama oleh Table Expedition dan LED Ambience.</p>
    </div>
    <a href="{{ route('admin.rfid-tags.create') }}" class="btn btn-eiger flex-shrink-0"><i class="bi bi-plus-lg me-1"></i>Daftarkan RFID</a>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['label' => 'Total RFID', 'value' => $summary['total'], 'icon' => 'bi-broadcast-pin', 'color' => '#334155'],
        ['label' => 'Terpetakan', 'value' => $summary['mapped'], 'icon' => 'bi-link-45deg', 'color' => '#15803d'],
        ['label' => 'Belum Dipetakan', 'value' => $summary['unassigned'], 'icon' => 'bi-exclamation-circle', 'color' => '#d97706'],
        ['label' => 'Pernah Dipindai', 'value' => $summary['scanned'], 'icon' => 'bi-clock-history', 'color' => '#2563eb'],
    ] as $item)
        <div class="col-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:42px;height:42px;background:{{ $item['color'] }}"><i class="bi {{ $item['icon'] }} fs-5"></i></span>
                    <div><div class="text-muted small">{{ $item['label'] }}</div><div class="fs-4 fw-bold lh-1">{{ $item['value'] }}</div></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.rfid-tags.index') }}" class="row g-3">
            <div class="col-12 col-lg-7">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari UID, label, SKU, atau nama produk..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-7 col-lg-3">
                <select name="mapping" class="form-select">
                    <option value="">Semua status pemetaan</option>
                    <option value="mapped" @selected(request('mapping') === 'mapped')>Terpetakan</option>
                    <option value="unassigned" @selected(request('mapping') === 'unassigned')>Belum dipetakan</option>
                </select>
            </div>
            <div class="col-12 col-sm-5 col-lg-2 d-flex gap-2">
                <button class="btn btn-dark flex-grow-1"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                @if(request()->hasAny(['search', 'mapping']))
                    <a href="{{ route('admin.rfid-tags.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th class="ps-3" style="min-width:240px">UID / Label RFID</th>
                    <th style="min-width:220px">Produk Terhubung</th>
                    <th style="min-width:130px">SKU Utama</th>
                    <th style="min-width:145px">Status</th>
                    <th style="min-width:155px">Terdaftar</th>
                    <th style="min-width:175px">Scan Terakhir</th>
                    <th class="pe-3 text-end" style="width:110px">Aksi</th>
                </tr></thead>
                <tbody>
                    @forelse($tags as $tag)
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white flex-shrink-0" style="width:38px;height:38px;background:linear-gradient(135deg,#f59e0b,#b45309)"><i class="bi bi-broadcast-pin"></i></span>
                                    <div class="overflow-hidden">
                                        <code class="fw-semibold text-dark font-monospace text-break">{{ $tag->uid }}</code>
                                        <div class="text-muted small text-truncate">{{ $tag->name ?: 'Tanpa label fisik' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($tag->product)
                                    <div class="fw-semibold text-dark">{{ $tag->product->name }}</div><div class="text-muted small">Data produk dari PIM</div>
                                @else
                                    <span class="text-muted fst-italic small">Belum terhubung ke produk</span>
                                @endif
                            </td>
                            <td>
                                @if($tag->product)<code class="px-2 py-1 rounded bg-light text-dark">{{ $tag->product->sku }}</code>@else<span class="text-muted">—</span>@endif
                            </td>
                            <td>
                                @if($tag->product_id)
                                    <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle me-1"></i>Terpetakan</span>
                                @else
                                    <span class="badge rounded-pill text-bg-warning"><i class="bi bi-exclamation-circle me-1"></i>Belum Dipetakan</span>
                                @endif
                            </td>
                            <td><div class="fw-semibold">{{ $tag->created_at->format('d M Y') }}</div><small class="text-muted">{{ $tag->created_at->format('H:i') }} WIB</small></td>
                            <td>
                                @if($tag->last_scanned_at)
                                    <div class="fw-semibold">{{ $tag->last_scanned_at->format('d M Y') }}</div><small class="text-muted">{{ $tag->last_scanned_at->format('H:i:s') }} WIB</small>
                                @else
                                    <span class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Belum pernah dipindai</span>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group-actions">
                                    <a href="{{ route('admin.rfid-tags.edit', $tag) }}" class="btn btn-sm btn-outline-warning btn-icon" title="Atur Pemetaan"><i class="bi bi-pencil-fill"></i></a>
                                    <form action="{{ route('admin.rfid-tags.destroy', $tag) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus RFID Tag {{ $tag->uid }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">
                            @include('admin.partials.empty-state', ['icon' => 'bi-broadcast', 'title' => 'Belum ada RFID Tag', 'sub' => 'Daftarkan UID/EPC untuk mulai menghubungkan tag fisik dengan produk.', 'actionUrl' => route('admin.rfid-tags.create'), 'actionLabel' => 'Daftarkan RFID'])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', ['paginator' => $tags, 'resource' => 'tag'])
    </div>
</div>
@endsection
