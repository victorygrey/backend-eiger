@extends('layouts.admin')
@section('title', 'Manajemen User')
@section('page-title', 'Manajemen User')
@section('breadcrumb-items')<li class="breadcrumb-item active">User & Role</li>@endsection
@section('content')
@php
    $userMap = $users->getCollection()->keyBy('id')->map(fn ($u) => $u->only(['id','name','email','role','job_title','is_active']));
    $oldUser = old('form_user_id') ? ['id'=>(int)old('form_user_id'),'name'=>old('name'),'email'=>old('email'),'role'=>old('role'),'job_title'=>old('job_title'),'is_active'=>(bool)old('is_active')] : null;
@endphp
<div class="d-flex justify-content-between align-items-center mb-4"><div><h4 class="fw-bold mb-1">Manajemen User</h4><p class="text-muted mb-0">Kelola akun, jabatan, role, dan status akses CMS.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('admin.roles.index') }}">Kelola Role</a><button class="btn btn-eiger" data-bs-toggle="modal" data-bs-target="#userModal" id="createUserButton">Buat User</button></div></div>
@if(session('invitation_link'))
<div class="card border-warning mb-4"><div class="card-body"><h6 class="fw-bold">Tautan atur password (berlaku 24 jam)</h6><p class="small text-muted">Tautan hanya tampil setelah dibuat. Berikan kepada user yang sesuai. Email belum dikirim otomatis.</p><label class="visually-hidden" for="invitationLink">Tautan aktivasi</label><div class="input-group"><input class="form-control" id="invitationLink" value="{{ session('invitation_link') }}" readonly><button type="button" class="btn btn-outline-secondary" id="copyInvitation">Salin Tautan</button></div></div></div>
@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ implode(' ', $errors->all()) }}</div>@endif
<div class="card border-0 shadow-sm"><div class="card-body"><form class="d-flex flex-wrap gap-2" method="get"><input class="form-control" style="max-width:320px" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, atau jabatan..." aria-label="Cari user"><select class="form-select w-auto" name="role" aria-label="Filter role"><option value="">Semua Role</option>@foreach($roles as $role)<option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>@endforeach</select><select class="form-select w-auto" name="status" aria-label="Filter status"><option value="">Semua Status</option><option value="1" @selected(request('status') === '1')>Aktif</option><option value="0" @selected(request('status') === '0')>Non Aktif</option></select><button class="btn btn-dark">Cari</button></form></div>
<div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>No.</th><th>Nama User</th><th>Email</th><th>Role</th><th>Jabatan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
@forelse($users as $u)<tr><td>{{ $users->firstItem() + $loop->index }}</td><td class="fw-semibold">{{ $u->name }}</td><td>{{ $u->email }}</td><td><span class="badge bg-light text-dark border">{{ $u->roleName() }}</span></td><td>{{ $u->job_title ?: '-' }}</td><td><span class="badge {{ !$u->isActive() ? 'bg-secondary' : ($u->awaitingSetup() ? 'bg-warning text-dark' : 'bg-success') }}">{{ !$u->isActive() ? 'Non Aktif' : ($u->awaitingSetup() ? 'Menunggu Aktivasi' : 'Aktif') }}</span></td><td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-primary edit-user" data-id="{{ $u->id }}">Ubah</button>@if($u->awaitingSetup() && $u->isActive())<form class="d-inline" method="post" action="{{ route('admin.users.invitation', $u) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Tautan Baru</button></form>@endif @if($u->id !== auth()->id())<button class="btn btn-sm btn-outline-danger delete-user" data-id="{{ $u->id }}">Hapus</button>@endif</td></tr>
@empty<tr><td colspan="7" class="text-center text-muted py-5">Tidak ada user yang sesuai.</td></tr>@endforelse</tbody></table></div><div class="card-footer bg-white">{{ $users->links() }}</div></div>
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" action="{{ route('admin.users.store') }}" id="userForm" class="modal-content border-0 shadow">@csrf<input type="hidden" name="_method" id="userMethod" value="POST"><input type="hidden" name="form_user_id" id="formUserId"><div class="modal-header"><h5 class="modal-title fw-bold" id="userModalTitle">Buat User</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body p-4">
<label class="form-label" for="userName">Nama <span class="text-danger">*</span></label><input class="form-control mb-3" id="userName" name="name" maxlength="100" required>
<label class="form-label" for="userEmail">Email <span class="text-danger">*</span></label><input class="form-control mb-3" id="userEmail" name="email" type="email" maxlength="150" required>
<label class="form-label" for="userRole">Role <span class="text-danger">*</span></label><input type="hidden" name="role" id="userRoleMirror" disabled><select class="form-select mb-3" id="userRole" name="role" required><option value="">Pilih Role</option>@foreach($roles as $role)<option value="{{ $role->slug }}" data-active="{{ $role->is_active ? 1 : 0 }}" @disabled(!$role->is_active)>{{ $role->name }}{{ !$role->is_active ? ' (Non Aktif)' : '' }}</option>@endforeach</select>
<label class="form-label" for="userJob">Jabatan <span class="text-danger">*</span></label><input class="form-control mb-3" id="userJob" name="job_title" maxlength="150" required>
<input type="hidden" name="is_active" value="0"><div class="form-check form-switch" id="userStatusGroup"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="userActive" checked><label class="form-check-label" for="userActive">Aktifkan Akun</label></div><div class="small text-muted mt-3" id="invitationHelp">Setelah dibuat, user mengatur password melalui tautan aktivasi sekali pakai.</div><div class="small text-warning mt-2 d-none" id="selfUserNote">Role dan status akun sendiri tidak dapat diubah.</div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-eiger" id="userSave">Buat</button></div></form></div></div>
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" id="deleteUserForm" class="modal-content">@csrf @method('DELETE')<div class="modal-header bg-danger text-white"><h5 class="modal-title" id="deleteUserTitle">Hapus User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body">Hapus akun <strong id="deleteUserName"></strong>? User ini tidak dapat mengakses CMS lagi.</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger">Hapus User</button></div></form></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const users = @json($userMap);
    const form = document.getElementById('userForm');
    const modal = document.getElementById('userModal');
    const ownId = @json(auth()->id());
    const base = @json(url('admin/users'));
    const updateValidity = () => document.getElementById('userSave').disabled = !form.checkValidity();
    form.addEventListener('input', updateValidity);
    form.addEventListener('change', updateValidity);
    function openUser(user = null) {
        form.reset(); form.action = user ? `${base}/${user.id}` : base;
        document.getElementById('userMethod').value = user ? 'PUT' : 'POST';
        document.getElementById('formUserId').value = user?.id || '';
        document.getElementById('userModalTitle').textContent = user ? 'Ubah User' : 'Buat User';
        document.getElementById('userSave').textContent = user ? 'Simpan Perubahan' : 'Buat';
        for (const [id, key] of [['userName','name'],['userEmail','email'],['userRole','role'],['userJob','job_title']]) document.getElementById(id).value = user?.[key] || '';
        document.getElementById('userActive').checked = user ? !!user.is_active : true;
        [...document.getElementById('userRole').options].forEach(option => option.disabled = option.dataset.active === '0' && option.value !== user?.role);
        const self = user?.id === ownId;
        document.getElementById('userRole').disabled = self;
        document.getElementById('userRoleMirror').disabled = !self;
        document.getElementById('userRoleMirror').value = user?.role || '';
        document.getElementById('userStatusGroup').classList.toggle('d-none', self);
        document.getElementById('selfUserNote').classList.toggle('d-none', !self);
        document.getElementById('invitationHelp').classList.toggle('d-none', !!user);
        updateValidity();
    }
    document.getElementById('createUserButton').addEventListener('click', () => openUser());
    document.querySelectorAll('.edit-user').forEach(button => button.addEventListener('click', () => { openUser(users[button.dataset.id]); bootstrap.Modal.getOrCreateInstance(modal).show(); }));
    document.querySelectorAll('.delete-user').forEach(button => button.addEventListener('click', () => { const user = users[button.dataset.id]; document.getElementById('deleteUserName').textContent = user.name; document.getElementById('deleteUserForm').action = `${base}/${user.id}`; bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteUserModal')).show(); }));
    document.getElementById('copyInvitation')?.addEventListener('click', async event => { const input = document.getElementById('invitationLink'); try { await navigator.clipboard.writeText(input.value); event.target.textContent = 'Tersalin'; } catch { input.select(); event.target.textContent = 'Salin teks yang dipilih'; } });
    @if($errors->any() && old('name'))
    openUser(@json($oldUser));
    @if(!old('form_user_id'))
    document.getElementById('userName').value = @json(old('name')); document.getElementById('userEmail').value = @json(old('email')); document.getElementById('userRole').value = @json(old('role')); document.getElementById('userJob').value = @json(old('job_title'));
    @endif
    bootstrap.Modal.getOrCreateInstance(modal).show();
    @endif
});
</script>
@endpush
