@extends('layouts.admin')

@section('title', 'Manajemen User & Hak Akses')
@section('page-title', 'Manajemen User & Hak Akses')

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted">Dashboard</a></li>
    <li class="breadcrumb-item active">Manajemen User</li>
@endsection

@section('content')

{{-- ===== STAT CARDS ===== --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value">{{ $stats['total'] }}</div>
                    <div class="stat-label">Total Pengguna</div>
                </div>
                <div class="stat-icon" style="background: rgba(232, 80, 10, 0.1); color: var(--eiger-orange);">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-danger">{{ $stats['superadmins'] }}</div>
                    <div class="stat-label">SuperAdmin (Full Access)</div>
                </div>
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-primary">{{ $stats['admins'] }}</div>
                    <div class="stat-label">Store Admin (Operational)</div>
                </div>
                <div class="stat-icon" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-value text-success">{{ $stats['active'] }}</div>
                    <div class="stat-label">Akun Aktif</div>
                </div>
                <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===== ACTIONS & FILTER BAR ===== --}}
<div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-body p-3">
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
            <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
                <div class="input-group" style="max-width: 320px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari nama atau email..." value="{{ request('search') }}">
                </div>

                <select name="role" class="form-select" style="max-width: 170px;" onchange="this.form.submit()">
                    <option value="">Semua Peran</option>
                    <option value="superadmin" {{ request('role') === 'superadmin' ? 'selected' : '' }}>SuperAdmin</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Store Admin</option>
                </select>

                <select name="status" class="form-select" style="max-width: 160px;" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Nonaktif</option>
                </select>

                @if(request()->hasAny(['search', 'role', 'status']))
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Reset
                    </a>
                @endif
            </form>

            <button type="button" class="btn btn-eiger text-white d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill"></i>
                <span>Tambah Pengguna</span>
            </button>
        </div>
    </div>
</div>

{{-- ===== USERS TABLE ===== --}}
<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="table-responsive">
        <table class="table align-middle mb-0 table-hover">
            <thead class="table-light">
                <tr>
                    <th class="ps-4" style="width: 50px;">#</th>
                    <th>Nama & Email</th>
                    <th>Peran (Role)</th>
                    <th>Status Akun</th>
                    <th>Terakhir Login</th>
                    <th class="text-end pe-4" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $index => $u)
                    <tr>
                        <td class="ps-4 text-muted">{{ $users->firstItem() + $index }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: {{ $u->isSuperAdmin() ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 'linear-gradient(135deg, #0ea5e9, #0284c7)' }}; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem;">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $u->name }}</div>
                                    <div class="text-muted small">{{ $u->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($u->isSuperAdmin())
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                    <i class="bi bi-shield-lock-fill me-1"></i> SuperAdmin
                                </span>
                            @else
                                <span class="badge bg-info-subtle text-primary border border-info-subtle px-2 py-1">
                                    <i class="bi bi-person-badge me-1"></i> Store Admin
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($u->isActive())
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Aktif
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                    <i class="bi bi-dash-circle-fill me-1"></i> Nonaktif
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($u->last_login_at)
                                <div class="small fw-semibold text-dark">{{ $u->last_login_at->timezone('Asia/Jakarta')->format('d M Y') }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">{{ $u->last_login_at->timezone('Asia/Jakarta')->format('H:i') }} WIB</div>
                            @else
                                <span class="text-muted small">Belum pernah</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-icon"
                                        title="Edit Pengguna"
                                        onclick="openEditUserModal({{ json_encode([
                                            'id'        => $u->id,
                                            'name'      => $u->name,
                                            'email'     => $u->email,
                                            'role'      => $u->role,
                                            'is_active' => (bool)$u->is_active,
                                            'is_self'   => $u->id === auth()->id(),
                                        ]) }})">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                @if($u->id !== auth()->id())
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-icon"
                                            title="Hapus Pengguna"
                                            onclick="openDeleteUserModal({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ $u->role }}')">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-people"></i></div>
                                <div class="empty-title">Tidak ada akun pengguna ditemukan</div>
                                <div class="empty-sub">Coba ubah kata kunci pencarian atau filter peran/status.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="card-footer bg-white py-3 border-0">
            {{ $users->links() }}
        </div>
    @endif
</div>

{{-- ===== MODAL TAMBAH PENGGUNA ===== --}}
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('admin.users.store') }}" class="modal-content border-0 shadow">
            @csrf
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addUserModalLabel">
                    <i class="bi bi-person-plus-fill text-danger me-2"></i> Tambah Pengguna Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label" for="add_name">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="add_name" class="form-control" placeholder="Contoh: Ahmad Fauzi" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="add_email">Alamat Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="add_email" class="form-control" placeholder="fauzi@eigeradventure.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="add_password">Kata Sandi Awal <span class="text-danger">*</span></label>
                    <input type="password" name="password" id="add_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="add_role">Peran (Role) <span class="text-danger">*</span></label>
                    <select name="role" id="add_role" class="form-select" required>
                        <option value="admin">Store Admin (Operasional & Display)</option>
                        <option value="superadmin">SuperAdmin (Full System Access)</option>
                    </select>
                    <div class="form-text">
                        <strong>SuperAdmin:</strong> Akses ke manajemen user dan konfigurasi sistem.<br>
                        <strong>Store Admin:</strong> Akses ke produk, RFID, dan konfigurasi wahana digital.
                    </div>
                </div>

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active" value="1" checked>
                    <label class="form-check-label fw-semibold" for="add_is_active">Aktifkan Akun</label>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-eiger text-white">
                    <i class="bi bi-save me-1"></i> Simpan Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ===== MODAL EDIT PENGGUNA ===== --}}
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="editUserForm" class="modal-content border-0 shadow">
            @csrf
            @method('PUT')
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="editUserModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i> Edit Akun Pengguna
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label" for="edit_name">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="edit_email">Alamat Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="edit_password">Kata Sandi Baru (Opsional)</label>
                    <input type="password" name="password" id="edit_password" class="form-control" placeholder="Biarkan kosong jika tidak ingin mengubah kata sandi" minlength="6">
                </div>

                <div class="mb-3" id="edit_role_container">
                    <label class="form-label" for="edit_role">Peran (Role) <span class="text-danger">*</span></label>
                    <select name="role" id="edit_role" class="form-select" required>
                        <option value="admin">Store Admin (Operasional & Display)</option>
                        <option value="superadmin">SuperAdmin (Full System Access)</option>
                    </select>
                </div>

                <div class="form-check form-switch mt-3" id="edit_status_container">
                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                    <label class="form-check-label fw-semibold" for="edit_is_active">Status Akun Aktif</label>
                </div>
                <div id="self_warning" class="alert alert-warning small mt-3 d-none">
                    <i class="bi bi-info-circle-fill me-1"></i> Ini adalah akun Anda sendiri yang sedang aktif. Anda tidak dapat mengubah peran atau menonaktifkan akun sendiri.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ===== MODAL HAPUS PENGGUNA ===== --}}
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="deleteUserForm" class="modal-content border-0 shadow">
            @csrf
            @method('DELETE')
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="deleteUserModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Konfirmasi Hapus Pengguna
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-3" style="font-size: 3rem;">
                    <i class="bi bi-person-x-fill"></i>
                </div>
                <h5>Apakah Anda yakin ingin menghapus akun ini?</h5>
                <p class="text-muted mb-0">Pengguna <strong id="deleteUserName"></strong> (<span id="deleteUserRole"></span>) akan dihapus secara permanen dan tidak dapat mengakses CMS lagi.</p>
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger px-4">
                    <i class="bi bi-trash-fill me-1"></i> Ya, Hapus Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openEditUserModal(user) {
    const form = document.getElementById('editUserForm');
    form.action = '{{ url("admin/users") }}/' + user.id;

    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_is_active').checked = user.is_active;

    const roleContainer = document.getElementById('edit_role_container');
    const statusContainer = document.getElementById('edit_status_container');
    const selfWarning = document.getElementById('self_warning');

    if (user.is_self) {
        document.getElementById('edit_role').disabled = true;
        document.getElementById('edit_is_active').disabled = true;
        selfWarning.classList.remove('d-none');
    } else {
        document.getElementById('edit_role').disabled = false;
        document.getElementById('edit_is_active').disabled = false;
        selfWarning.classList.add('d-none');
    }

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function openDeleteUserModal(id, name, role) {
    const form = document.getElementById('deleteUserForm');
    form.action = '{{ url("admin/users") }}/' + id;

    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteUserRole').textContent = role === 'superadmin' ? 'SuperAdmin' : 'Store Admin';

    const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    modal.show();
}
</script>
@endpush

@endsection
