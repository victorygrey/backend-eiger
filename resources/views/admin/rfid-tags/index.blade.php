@extends('layouts.admin')

@section('title', 'Master RFID Tags')
@section('page-title', 'Master RFID Tags')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Manajemen Data</a></li>
    <li class="breadcrumb-item active">RFID Tags</li>
@endsection

@push('styles')
<style>
    .rfid-channel-card { border: 0; border-radius: 14px; box-shadow: 0 10px 30px rgba(26, 31, 44, .07); overflow: hidden; }
    .rfid-channel-table { min-width: 1100px; }
    .rfid-channel-table thead th { background: #171b2d; border: 0; color: #ff6a00; font-size: .72rem; letter-spacing: .06em; padding: 1rem; text-transform: uppercase; white-space: nowrap; }
    .rfid-channel-table tbody td { border-color: #eceff3; padding: .85rem 1rem; }
    .rfid-channel-table tbody tr:hover { background: rgba(255, 106, 0, .045); }
    .rfid-channel-control { align-items: center; display: inline-flex; gap: .55rem; margin: 0; min-width: 126px; }
    .rfid-channel-control .form-check-input { cursor: pointer; height: 1.45rem; margin: 0; width: 2.65rem; }
    .rfid-channel-control .form-check-input:checked { background-color: #ff6a00; border-color: #ff6a00; }
    .rfid-channel-control .form-check-input:disabled { cursor: not-allowed; filter: grayscale(.35); opacity: .45; }
    .rfid-channel-label { color: #8b93a1; font-size: .75rem; font-weight: 700; white-space: nowrap; }
    .rfid-channel-control .form-check-input:checked + .rfid-channel-label { color: #198754; }
    .rfid-lock-toolbar { align-items: center; background: #f8f9fb; border-bottom: 1px solid #e8ebf0; display: flex; gap: .85rem; justify-content: space-between; padding: .85rem 1rem; }
    .rfid-lock-indicator { align-items: center; display: flex; gap: .7rem; min-width: 0; }
    .rfid-lock-icon { align-items: center; background: rgba(220, 53, 69, .1); border-radius: 10px; color: #b02a37; display: inline-flex; flex: 0 0 auto; height: 38px; justify-content: center; width: 38px; }
    .rfid-lock-title { color: #343a46; font-size: .8rem; font-weight: 800; }
    .rfid-lock-help { color: #7b8494; font-size: .72rem; }
    .rfid-lock-toolbar.is-unlocked { background: rgba(255, 193, 7, .08); border-bottom-color: rgba(255, 193, 7, .3); }
    .rfid-lock-toolbar.is-unlocked .rfid-lock-icon { background: rgba(255, 193, 7, .17); color: #8a6400; }
    .rfid-lock-toolbar.is-unlocked .rfid-lock-title { color: #805b00; }
    @media (max-width: 575.98px) { .rfid-lock-toolbar { align-items: stretch; flex-direction: column; } .rfid-lock-toolbar .btn { width: 100%; } }
</style>
@endpush

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

<div class="card rfid-channel-card">
    <div class="card-body p-0">
        <div class="rfid-lock-toolbar {{ $channelSettingsUnlocked ? 'is-unlocked' : '' }}">
            <div class="rfid-lock-indicator">
                <span class="rfid-lock-icon"><i class="bi {{ $channelSettingsUnlocked ? 'bi-unlock-fill' : 'bi-lock-fill' }}"></i></span>
                <div>
                    <div class="rfid-lock-title">Pengaturan wahana RFID {{ $channelSettingsUnlocked ? 'terbuka' : 'terkunci' }}</div>
                    <div class="rfid-lock-help">
                        {{ $channelSettingsUnlocked
                            ? 'Switch dapat diubah. Kunci kembali setelah selesai mengatur RFID.'
                            : 'Switch LED Ambience dan Table Expedition dikunci untuk mencegah perubahan tidak sengaja.' }}
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.rfid-tags.channel-lock.update') }}">
                @csrf @method('PATCH')
                <input type="hidden" name="unlocked" value="{{ $channelSettingsUnlocked ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm {{ $channelSettingsUnlocked ? 'btn-warning' : 'btn-outline-dark' }} flex-shrink-0">
                    <i class="bi {{ $channelSettingsUnlocked ? 'bi-lock-fill' : 'bi-unlock-fill' }} me-1"></i>{{ $channelSettingsUnlocked ? 'Kunci Pengaturan' : 'Unlock Pengaturan' }}
                </button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table rfid-channel-table align-middle mb-0">
                <thead><tr>
                    <th class="ps-3" style="min-width:240px">UID / Label RFID</th>
                    <th style="min-width:255px">Produk / SKU Terhubung</th>
                    <th style="min-width:145px">Status</th>
                    <th style="min-width:155px">LED Ambience</th>
                    <th style="min-width:170px">Table Expedition</th>
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
                                    <div class="fw-semibold text-dark">{{ $tag->product->name }}</div>
                                    <code class="d-inline-block mt-1 px-2 py-1 rounded bg-light text-dark">{{ $tag->product->sku }}</code>
                                @else
                                    <span class="text-muted fst-italic small">Belum terhubung ke produk</span>
                                @endif
                            </td>
                            <td>
                                @if($tag->product_id)
                                    <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle me-1"></i>Terpetakan</span>
                                @else
                                    <span class="badge rounded-pill text-bg-warning"><i class="bi bi-exclamation-circle me-1"></i>Belum Dipetakan</span>
                                @endif
                            </td>
                            <td>
                                @php($ledActive = (bool) ($tag->product_id && $tag->ledAmbienceItem?->is_active && (int) $tag->ledAmbienceItem->product_id === (int) $tag->product_id))
                                <form method="POST" action="{{ route('admin.rfid-tags.channel.update', $tag) }}" class="rfid-channel-control">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="channel" value="led_ambience">
                                    <input type="hidden" name="active" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="active" value="1"
                                        id="led-rfid-{{ $tag->id }}" @checked($ledActive) @disabled(!$channelSettingsUnlocked || !$tag->product_id) onchange="this.form.submit()">
                                    <label class="rfid-channel-label" for="led-rfid-{{ $tag->id }}">{{ $ledActive ? 'Active' : 'Not Active' }}</label>
                                </form>
                                @if(!$tag->product_id)<small class="text-muted d-block mt-1">Hubungkan produk dahulu</small>@endif
                            </td>
                            <td>
                                @php($tableActive = (bool) ($tag->product_id && $tag->tableExpeditionItem?->is_active && (int) $tag->tableExpeditionItem->product_id === (int) $tag->product_id))
                                <form method="POST" action="{{ route('admin.rfid-tags.channel.update', $tag) }}" class="rfid-channel-control">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="channel" value="table_expedition">
                                    <input type="hidden" name="active" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="active" value="1"
                                        id="table-rfid-{{ $tag->id }}" @checked($tableActive) @disabled(!$channelSettingsUnlocked || !$tag->product_id) onchange="this.form.submit()">
                                    <label class="rfid-channel-label" for="table-rfid-{{ $tag->id }}">{{ $tableActive ? 'Active' : 'Not Active' }}</label>
                                </form>
                                @if(!$tag->product_id)<small class="text-muted d-block mt-1">Hubungkan produk dahulu</small>@endif
                            </td>
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
