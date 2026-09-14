@extends('layouts.admin')

@section('title', 'Manajemen RFID Tags')
@section('page-title', 'Daftar RFID Tag')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Manajemen Data</a></li>
    <li class="breadcrumb-item active">RFID Tags</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-broadcast-pin text-warning me-2"></i>Manajemen RFID Tag</h4>
        <p class="text-muted small mb-0">Daftar UID RFID dan tanggal terscan.</p>
    </div>
    <a href="{{ route('admin.rfid-tags.create') }}" class="btn btn-eiger">
        <i class="bi bi-plus-lg me-1"></i>Tambah RFID Tag
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.rfid-tags.index') }}" class="row g-3">
            <div class="col-12 col-md-8">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan UID atau Nama RFID..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button class="btn btn-dark w-100"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                @if(request('search'))
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
                <thead>
                    <tr>
                        <th class="ps-3" style="width: 70px;">#</th>
                        <th>UID RFID</th>
                        <th>Nama / Label Tag</th>
                        <th style="width: 220px;">Tanggal Terscan</th>
                        <th class="pe-3 text-end" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tags as $i => $tag)
                        <tr>
                            <td class="ps-3 text-muted">{{ $tags->firstItem() + $i }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="p-2 rounded text-white" style="background:linear-gradient(135deg,#f59e0b,#b45309)">
                                        <i class="bi bi-broadcast-pin"></i>
                                    </div>
                                    <code class="fw-semibold fs-6 text-dark font-monospace">{{ $tag->uid }}</code>
                                </div>
                            </td>
                            <td>
                                @if($tag->name)
                                    <div class="fw-bold text-dark">{{ $tag->name }}</div>
                                @else
                                    <span class="text-muted fst-italic small">Belum diberi nama</span>
                                @endif
                                @if($tag->product)
                                    <small class="text-muted d-block font-monospace"><i class="bi bi-box-seam me-1"></i>{{ $tag->product->sku }} — {{ $tag->product->name }}</small>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-clock-history text-secondary fs-5"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $tag->created_at->format('d M Y') }}</div>
                                        <small class="text-muted">{{ $tag->created_at->format('H:i:s') }} WIB</small>
                                    </div>
                                </div>
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group-actions">
                                    <a href="{{ route('admin.rfid-tags.edit', $tag) }}" class="btn btn-sm btn-outline-warning btn-icon" title="Edit Tag">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form action="{{ route('admin.rfid-tags.destroy', $tag) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Yakin hapus RFID Tag {{ $tag->uid }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            @include('admin.partials.empty-state', [
                                'icon'        => 'bi-broadcast',
                                'title'       => 'Belum ada RFID Tag',
                                'sub'         => 'Daftarkan atau scan UID RFID untuk menambahkan data baru.',
                                'actionUrl'   => route('admin.rfid-tags.create'),
                                'actionLabel' => 'Tambah RFID Tag',
                            ])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', [
            'paginator' => $tags,
            'resource'  => 'tag',
        ])
    </div>
</div>
@endsection
