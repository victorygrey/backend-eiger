@extends('layouts.admin')

@section('title', 'Edit Print Rule')
@section('page-title', 'Edit Print Rule')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.print-rules.index') }}" class="text-decoration-none">Print Rules</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.print-rules.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <div class="p-2 rounded text-white me-2" style="background:linear-gradient(135deg,#06b6d4,#0e7490)">
            <i class="bi bi-printer-fill"></i>
        </div>
        <span>Edit Print Rule</span>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.print-rules.update', $printRule) }}" method="POST">
            @csrf @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Minimum Transaksi (Rp)</label>
                    <input type="number" name="minimum_transaction" min="0" step="0.01"
                        value="{{ old('minimum_transaction', $printRule->minimum_transaction) }}"
                        class="form-control @error('minimum_transaction') is-invalid @enderror">
                    @error('minimum_transaction') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch me-4">
                        <input type="checkbox" name="require_membership" value="1" id="require_membership"
                            class="form-check-input"
                            {{ old('require_membership', $printRule->require_membership) ? 'checked' : '' }}>
                        <label for="require_membership" class="form-check-label">Wajib Membership</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" name="enabled" value="1" id="enabled"
                            class="form-check-input"
                            {{ old('enabled', $printRule->enabled) ? 'checked' : '' }}>
                        <label for="enabled" class="form-check-label">Rule Aktif</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-eiger"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                <a href="{{ route('admin.print-rules.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
