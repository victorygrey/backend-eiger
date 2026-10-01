@extends('layouts.admin')

@section('title', 'Histori Foto & Print')
@section('page-title', 'Histori Foto & Print')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Operasional</li>
    <li class="breadcrumb-item active">Histori Foto &amp; Print</li>
@endsection

@php
    $badgeClass = [
        'queued' => 'bg-secondary',
        'printing' => 'bg-primary',
        'success' => 'bg-success',
        'failed' => 'bg-danger',
        'cancelled' => 'bg-dark',
    ];
    $statusLabel = [
        'queued' => 'Menunggu',
        'printing' => 'Sedang dicetak',
        'success' => 'Berhasil',
        'failed' => 'Gagal',
        'cancelled' => 'Dibatalkan',
    ];
@endphp

@push('styles')
<style>
    .print-history-hero { background: linear-gradient(135deg, #171b2d, #303a59); border-radius: 16px; color: #fff; overflow: hidden; padding: 1.5rem; position: relative; }
    .print-history-hero::after { background: #ff6500; border-radius: 50%; content: ''; height: 180px; opacity: .16; position: absolute; right: -65px; top: -80px; width: 180px; }
    .print-stat { background: #fff; border: 1px solid #e9ecef; border-radius: 14px; height: 100%; padding: 1rem; }
    .print-stat-icon { align-items: center; border-radius: 10px; display: flex; height: 38px; justify-content: center; width: 38px; }
    .capture-thumb { align-items: center; background: #f4f6f8; border: 1px dashed #c7ced7; border-radius: 10px; color: #8290a3; display: flex; height: 52px; justify-content: center; overflow: hidden; width: 52px; }
    .capture-thumb img { height: 100%; object-fit: cover; width: 100%; }
    .code-label { font-size: .75rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
</style>
@endpush

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-printer-fill text-primary me-2"></i>Histori Foto &amp; Print</h4>
        <p class="text-muted mb-0">Jejak pengambilan foto AI Fit &amp; Go dan setiap proses cetaknya.</p>
    </div>
    <a href="{{ route('admin.fit-and-go.index') }}" class="btn btn-outline-secondary"><i class="bi bi-display me-1"></i>Buka AI Fit &amp; Go</a>
</div>

<div class="print-history-hero mb-4">
    <div class="position-relative" style="z-index:1; max-width: 760px;">
        <div class="text-uppercase fw-bold small opacity-75 mb-2" style="letter-spacing:.08em">Data operasional terpisah</div>
        <h5 class="fw-bold mb-2">Foto satu kali, riwayat cetak bisa berkali-kali.</h5>
        <p class="mb-0 text-white-50">Setiap foto tersambung ke perangkat AI Fit &amp; Go dan session. File nantinya disimpan di NAS, sementara status setiap antrian printer dicatat terpisah untuk audit dan penanganan gagal cetak.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="print-stat d-flex gap-3 align-items-center"><span class="print-stat-icon bg-primary-subtle text-primary"><i class="bi bi-camera-fill"></i></span><div><div class="text-muted small">Total Foto</div><div class="fs-4 fw-bold">{{ number_format($summary['captures']) }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="print-stat d-flex gap-3 align-items-center"><span class="print-stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></span><div><div class="text-muted small">Dalam Antrian</div><div class="fs-4 fw-bold">{{ number_format($summary['queued']) }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="print-stat d-flex gap-3 align-items-center"><span class="print-stat-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></span><div><div class="text-muted small">Cetak Berhasil</div><div class="fs-4 fw-bold">{{ number_format($summary['success']) }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="print-stat d-flex gap-3 align-items-center"><span class="print-stat-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-octagon"></i></span><div><div class="text-muted small">Perlu Ditangani</div><div class="fs-4 fw-bold">{{ number_format($summary['failed']) }}</div></div></div></div>
</div>

<div class="card border-0 shadow-sm rounded-3 mb-4"><div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-lg-4"><label class="form-label small fw-semibold">Cari kode foto, session, atau kode cetak</label><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Contoh: FT-20261001-001"></div></div>
        <div class="col-6 col-lg-2"><label class="form-label small fw-semibold">Perangkat</label><select name="device_id" class="form-select"><option value="">Semua perangkat</option>@foreach($devices as $device)<option value="{{ $device->id }}" @selected(request('device_id') == $device->id)>{{ $device->name }}</option>@endforeach</select></div>
        <div class="col-6 col-lg-2"><label class="form-label small fw-semibold">Status cetak terakhir</label><select name="status" class="form-select"><option value="">Semua status</option>@foreach($statusLabel as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-6 col-lg-2"><label class="form-label small fw-semibold">Dari tanggal</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-6 col-lg-2"><label class="form-label small fw-semibold">Sampai tanggal</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-eiger"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button><a href="{{ route('admin.print-history.index') }}" class="btn btn-light">Reset</a></div>
    </form>
</div></div>

<div class="card border-0 shadow-sm rounded-3 overflow-hidden">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><div><h6 class="fw-bold mb-1">Riwayat Terbaru</h6><small class="text-muted">Status yang ditampilkan adalah proses cetak paling akhir dari setiap foto.</small></div><span class="badge bg-light text-dark border">{{ $captures->total() }} foto</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Foto</th><th>Perangkat &amp; Session</th><th>Waktu Pengambilan</th><th>Print Terakhir</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        @forelse($captures as $capture)
            @php($job = $capture->latestPrintJob)
            <tr>
                <td><div class="d-flex align-items-center gap-2"><div class="capture-thumb">@if($capture->thumbnail_path)<img src="{{ $capture->thumbnail_path }}" alt="{{ $capture->capture_code }}">@else<i class="bi bi-image fs-5"></i>@endif</div><div><code class="fw-bold text-dark">{{ $capture->capture_code }}</code><div class="small text-muted">{{ $capture->photo_path ? 'Foto tersimpan di NAS' : 'Menunggu file dari perangkat' }}</div></div></div></td>
                <td><div class="fw-semibold">{{ $capture->device?->name ?? 'Perangkat sudah dihapus' }}</div><div class="small text-muted font-monospace">{{ $capture->session_reference ?: 'Session tidak tersedia' }}</div></td>
                <td><div>{{ $capture->captured_at->format('d M Y') }}</div><div class="small text-muted">{{ $capture->captured_at->format('H:i:s') }} WIB</div></td>
                <td>@if($job)<code>{{ $job->print_code }}</code><div class="small text-muted">{{ $job->printer_name ?: 'Printer belum dipilih' }} · {{ $job->copies }} salinan</div>@else<span class="text-muted">Belum ada permintaan cetak</span>@endif</td>
                <td>@if($job)<span class="badge {{ $badgeClass[$job->status] ?? 'bg-secondary' }}">{{ $statusLabel[$job->status] ?? ucfirst($job->status) }}</span>@else<span class="badge bg-light text-dark border">Belum dicetak</span>@endif</td>
                <td class="text-end"><a href="{{ route('admin.print-history.show', $capture) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center py-5"><i class="bi bi-camera fs-1 text-muted d-block mb-2"></i><h6 class="fw-bold">Belum ada histori foto</h6><p class="text-muted small mb-0">Data akan masuk saat perangkat AI Fit &amp; Go mulai mengirimkan foto dan status cetak.</p></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($captures->hasPages())<div class="card-footer bg-white">{{ $captures->links() }}</div>@endif
</div>
@endsection
