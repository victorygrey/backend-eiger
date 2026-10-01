@extends('layouts.admin')

@section('title', 'Detail Foto & Print')
@section('page-title', 'Detail Foto & Print')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.print-history.index') }}" class="text-decoration-none">Histori Foto &amp; Print</a></li>
    <li class="breadcrumb-item active">{{ $photoCapture->capture_code }}</li>
@endsection

@php
    $badgeClass = ['queued' => 'bg-secondary', 'printing' => 'bg-primary', 'success' => 'bg-success', 'failed' => 'bg-danger', 'cancelled' => 'bg-dark'];
    $statusLabel = ['queued' => 'Menunggu', 'printing' => 'Sedang dicetak', 'success' => 'Berhasil', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan'];
@endphp

@section('content')
<div class="mb-3"><a href="{{ route('admin.print-history.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali ke Histori</a></div>
<div class="row g-4">
    <div class="col-12 col-lg-5"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-4"><div><div class="text-muted small text-uppercase fw-bold">Kode foto</div><h5 class="fw-bold mb-0 font-monospace">{{ $photoCapture->capture_code }}</h5></div><span class="badge bg-primary-subtle text-primary border">AI Fit &amp; Go</span></div>
        <div class="rounded-3 bg-light border d-flex align-items-center justify-content-center" style="height:280px; overflow:hidden;">@if($photoCapture->photo_path)<img src="{{ $photoCapture->photo_path }}" alt="{{ $photoCapture->capture_code }}" class="w-100 h-100 object-fit-cover">@else<div class="text-center text-muted"><i class="bi bi-image fs-1 d-block mb-2"></i><div>Preview foto akan tersedia dari penyimpanan NAS.</div></div>@endif</div>
        <div class="small text-muted mt-3">Disk: <code>{{ $photoCapture->storage_disk }}</code> · Retensi sampai {{ $photoCapture->expires_at?->format('d M Y H:i') ?? 'belum ditentukan' }} WIB</div>
    </div></div></div>
    <div class="col-12 col-lg-7"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Informasi Pengambilan</h6>
        <div class="row g-3 mb-4"><div class="col-md-6"><div class="text-muted small">Perangkat</div><div class="fw-semibold">{{ $photoCapture->device?->name ?? 'Perangkat sudah dihapus' }}</div><div class="small text-muted">{{ $photoCapture->device?->location ?? 'Lokasi tidak tersedia' }}</div></div><div class="col-md-6"><div class="text-muted small">Session</div><code>{{ $photoCapture->session_reference ?: 'Tidak tersedia' }}</code></div><div class="col-md-6"><div class="text-muted small">Diambil pada</div><div class="fw-semibold">{{ $photoCapture->captured_at->format('d M Y, H:i:s') }} WIB</div></div><div class="col-md-6"><div class="text-muted small">Riwayat cetak</div><div class="fw-semibold">{{ $photoCapture->printJobs->count() }} permintaan</div></div></div>
        <h6 class="fw-bold mb-3"><i class="bi bi-printer text-primary me-2"></i>Log Proses Cetak</h6>
        <div class="list-group list-group-flush border rounded-3">@forelse($photoCapture->printJobs as $job)<div class="list-group-item p-3"><div class="d-flex justify-content-between gap-3"><div><div class="fw-semibold"><code>{{ $job->print_code }}</code> <span class="badge {{ $badgeClass[$job->status] ?? 'bg-secondary' }} ms-1">{{ $statusLabel[$job->status] ?? ucfirst($job->status) }}</span></div><div class="small text-muted mt-1">{{ $job->printer_name ?: 'Printer belum dipilih' }} · {{ $job->copies }} salinan · diminta {{ $job->requested_at->format('d M Y H:i:s') }} WIB</div>@if($job->error_message)<div class="small text-danger mt-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ $job->error_message }}</div>@endif</div><div class="small text-muted text-end">{{ $job->completed_at ? 'Selesai '.$job->completed_at->format('H:i:s') : 'Menunggu pembaruan' }}</div></div></div>@empty<div class="list-group-item text-center py-4 text-muted">Belum ada permintaan cetak untuk foto ini.</div>@endforelse</div>
    </div></div></div>
</div>
@endsection
