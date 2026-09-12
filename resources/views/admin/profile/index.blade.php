@extends('layouts.admin')

@section('title', 'Profil Saya & Pengaturan Akun')
@section('page-title', 'Profil Saya')

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted">Dashboard</a></li>
    <li class="breadcrumb-item active">Profil Saya</li>
@endsection

@section('content')

<div class="row g-4">
    {{-- Profile Info Card --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm text-center p-4" style="border-radius: 14px;">
            <div class="mx-auto mb-3" style="width: 84px; height: 84px; border-radius: 20px; background: {{ $user->isSuperAdmin() ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 'linear-gradient(135deg, #0ea5e9, #0284c7)' }}; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 700; box-shadow: 0 8px 20px rgba(0,0,0,0.15);">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <h5 class="fw-bold mb-1">{{ $user->name }}</h5>
            <div class="text-muted small mb-3">{{ $user->email }}</div>

            <div class="d-inline-block mx-auto mb-4">
                @if($user->isSuperAdmin())
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
                        <i class="bi bi-shield-lock-fill me-1"></i> SuperAdmin
                    </span>
                @else
                    <span class="badge bg-info-subtle text-primary border border-info-subtle px-3 py-2 fs-6">
                        <i class="bi bi-person-badge me-1"></i> Store Admin
                    </span>
                @endif
            </div>

            <div class="border-top pt-3 text-start small">
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Status Akun:</span>
                    <span class="fw-semibold text-success"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                </div>
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Terdaftar Sejak:</span>
                    <span class="fw-semibold text-dark">{{ $user->created_at->format('d M Y') }}</span>
                </div>
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Login Terakhir:</span>
                    <span class="fw-semibold text-dark">
                        {{ $user->last_login_at ? $user->last_login_at->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB' : 'Hari ini' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Profile & Password Form --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 14px;">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-gear text-danger me-2"></i> Perbarui Profil & Kata Sandi</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.profile.update') }}">
                    @csrf
                    @method('PUT')

                    <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Informasi Akun</h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="profile_name">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="profile_name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="profile_email">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="profile_email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Ubah Kata Sandi (Opsional)</h6>
                    <div class="alert alert-light border small text-muted mb-3">
                        <i class="bi bi-info-circle me-1"></i> Kosongkan form kata sandi baru jika Anda tidak ingin mengganti kata sandi.
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label" for="current_password">Kata Sandi Saat Ini</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Masukkan kata sandi saat ini untuk verifikasi">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_password">Kata Sandi Baru</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Minimal 6 karakter" minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_password_confirmation">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control" placeholder="Ulangi kata sandi baru">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-eiger text-white px-4">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
