@extends('layouts.admin')

@section('title', 'Sync Logs')
@section('page-title', 'Sync Logs')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Sistem</li>
    <li class="breadcrumb-item active">Sync Logs</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-arrow-repeat text-warning me-2"></i>Synchronization Logs</h4>
        <p class="text-muted small mb-0">Riwayat sinkronisasi sistem (read-only).</p>
    </div>
    <span class="badge-soft badge-gray-soft"><i class="bi bi-lock-fill"></i>Read-only</span>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.sync-logs.index') }}" class="row g-3">
            <div class="col-md-5">
                <select name="source" class="form-select">
                    <option value="">-- Semua Source --</option>
                    @foreach($sources as $s)
                        <option value="{{ $s }}" {{ request('source') == $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-dark w-100"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th class="pe-3">Synced At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $i => $log)
                        <tr>
                            <td class="ps-3 text-muted">{{ $logs->firstItem() + $i }}</td>
                            <td>
                                <span class="badge-soft badge-info-soft">
                                    <i class="bi bi-tag-fill"></i>{{ $log->source }}
                                </span>
                            </td>
                            <td>
                                @if(strtolower($log->status) === 'success')
                                    <span class="badge-soft badge-success-soft">
                                        <i class="bi bi-check-circle-fill"></i>{{ ucfirst($log->status) }}
                                    </span>
                                @elseif(in_array(strtolower($log->status), ['failed','error']))
                                    <span class="badge-soft badge-danger-soft">
                                        <i class="bi bi-x-circle-fill"></i>{{ ucfirst($log->status) }}
                                    </span>
                                @else
                                    <span class="badge-soft badge-warning-soft">
                                        <i class="bi bi-exclamation-circle-fill"></i>{{ ucfirst($log->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-muted">{{ \Str::limit($log->message, 80) }}</td>
                            <td class="pe-3 text-muted small">
                                <i class="bi bi-clock me-1"></i>
                                {{ $log->synced_at ? $log->synced_at->format('d M Y H:i') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            @include('admin.partials.empty-state', [
                                'icon'  => 'bi-inbox',
                                'title' => 'Belum ada log sinkronisasi',
                                'sub'   => 'Log akan muncul setelah command "php artisan care:sync" dijalankan.',
                            ])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', [
            'paginator' => $logs,
            'resource'  => 'log',
        ])
    </div>
</div>
@endsection
