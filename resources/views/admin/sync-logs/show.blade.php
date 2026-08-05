@extends('layouts.admin')

@section('title', 'Detail Sync Log')
@section('page-title', 'Detail Sync Log')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.sync-logs.index') }}" class="text-decoration-none">Sync Logs</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.sync-logs.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <div class="p-2 rounded text-white me-2" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <i class="bi bi-info-circle-fill"></i>
        </div>
        <span>Detail Sync Log #{{ $syncLog->id }}</span>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Source</dt>
            <dd class="col-sm-9"><span class="badge bg-info text-dark">{{ $syncLog->source }}</span></dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9">{{ ucfirst($syncLog->status) }}</dd>

            <dt class="col-sm-3">Message</dt>
            <dd class="col-sm-9">{{ $syncLog->message }}</dd>

            <dt class="col-sm-3">Synced At</dt>
            <dd class="col-sm-9">{{ $syncLog->synced_at?->format('d M Y H:i:s') ?? '—' }}</dd>

            <dt class="col-sm-3">Created</dt>
            <dd class="col-sm-9 text-muted">{{ $syncLog->created_at->format('d M Y H:i:s') }}</dd>
        </dl>
    </div>
</div>
@endsection
