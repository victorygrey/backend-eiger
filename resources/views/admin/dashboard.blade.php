@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Pusat Informasi & Statistik Digital Store</li>
@endsection

@section('content')
{{-- Stat Cards Row --}}
<div class="row g-4 mb-4">
    {{-- Total Products --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Total Products</div>
                <div class="stat-value mt-2">{{ number_format($stats['total_products']) }}</div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-box-seam"></i>
            </div>
        </div>
    </div>

    {{-- Total Zones --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Total Zones</div>
                <div class="stat-value mt-2">{{ number_format($stats['total_zones']) }}</div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-map"></i>
            </div>
        </div>
    </div>

    {{-- Total RFID Tags --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Total RFID Tags</div>
                <div class="stat-value mt-2">{{ number_format($stats['total_rfid_tags']) }}</div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-wifi"></i>
            </div>
        </div>
    </div>

    {{-- Total Print Rules --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Print Rules</div>
                <div class="stat-value mt-2">{{ number_format($stats['total_rules']) }}</div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-printer"></i>
            </div>
        </div>
    </div>
</div>

{{-- Status & Sync Info Row --}}
<div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-cpu me-2 text-danger"></i>Backend Status</span>
                <span class="badge badge-success-soft"><i class="bi bi-check-circle me-1"></i>Active</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-light rounded-3">
                        <i class="bi bi-hdd-network text-primary fs-3"></i>
                    </div>
                    <div>
                        <h6 class="mb-1 fw-bold">EIGER Digital Store REST API</h6>
                        <p class="text-muted small mb-0">SQLite Database • Laravel {{ app()->version() }} • PHP {{ PHP_VERSION }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-clock-history me-2 text-warning"></i>Last Synchronization</span>
                @if($lastSync)
                    <span class="badge {{ $lastSync->status === 'success' ? 'badge-success-soft' : ($lastSync->status === 'failed' ? 'badge-danger-soft' : 'badge-warning-soft') }}">
                        {{ strtoupper($lastSync->status) }}
                    </span>
                @endif
            </div>
            <div class="card-body">
                @if($lastSync)
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-light rounded-3">
                            <i class="bi bi-arrow-repeat text-warning fs-3"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold">Source: {{ $lastSync->source }}</h6>
                            <p class="text-muted small mb-0">{{ $lastSync->synced_at ? $lastSync->synced_at->format('d M Y, H:i:s') : 'Belum pernah' }}</p>
                            <small class="text-secondary">{{ $lastSync->message }}</small>
                        </div>
                    </div>
                @else
                    <p class="text-muted mb-0">Belum ada aktivitas sinkronisasi.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Recent Sync Logs Table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-task me-2 text-primary"></i>Recent Sync Logs</span>
        <a href="{{ route('admin.sync-logs.index') }}" class="btn btn-sm btn-outline-secondary">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Synced At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                        <tr>
                            <td>#{{ $log->id }}</td>
                            <td><span class="fw-semibold">{{ $log->source }}</span></td>
                            <td>
                                @if($log->status === 'success')
                                    <span class="badge badge-success-soft">Success</span>
                                @elseif($log->status === 'failed')
                                    <span class="badge badge-danger-soft">Failed</span>
                                @else
                                    <span class="badge badge-warning-soft">{{ ucfirst($log->status) }}</span>
                                @endif
                            </td>
                            <td class="text-truncate" style="max-width: 300px;">{{ $log->message }}</td>
                            <td>{{ $log->synced_at ? $log->synced_at->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Belum ada sync log.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
