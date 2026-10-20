@extends('layouts.admin')
@section('title', 'Role & Hak Akses')
@section('page-title', 'Role & Hak Akses')
@section('breadcrumb-items')<li class="breadcrumb-item active">Role</li>@endsection
@push('styles')
<style>.role-matrix th,.role-matrix td{padding:12px 9px;vertical-align:middle}.role-matrix input{width:18px;height:18px;accent-color:#e8500a;cursor:pointer}.role-matrix th:not(:first-child),.role-matrix td:not(:first-child){text-align:center}.role-matrix thead{position:sticky;top:0;z-index:1;background:#f3f5f8}.role-permissions{max-height:48vh;overflow:auto;border:1px solid #e5e8ee;border-radius:10px}.role-modal-header{background:#171b2d;color:white}.role-modal-footer{background:white}.role-tag{background:#fff0e5;color:#a63a00;border-radius:99px;padding:4px 9px;font-size:12px}</style>
@endpush
@section('content')
@php
    $oldRole = ['id'=>(int)old('form_role_id'),'name'=>old('name'),'permissions'=>old('permissions',[]),'is_active'=>(bool)old('is_active')];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h4 class="fw-bold mb-1">Role & Hak Akses</h4><p class="text-muted mb-0">Tentukan modul yang dapat dilihat, dikelola, dihapus, dan diexport oleh setiap role.</p></div><div class="d-flex gap-2"><a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Manajemen User</a><button class="btn btn-eiger" id="createRoleButton">Buat Role</button></div></div>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ implode(' ', $errors->all()) }}</div>@endif
<div class="card border-0 shadow-sm"><div class="card-body"><form class="d-flex gap-2" method="get"><input class="form-control" style="max-width:360px" name="search" value="{{ request('search') }}" placeholder="Masukkan nama role..." aria-label="Cari role"><button class="btn btn-dark">Cari</button></form></div><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>No.</th><th>Nama Role</th><th>Hak Akses</th><th>User</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
@forelse($roles as $role)<tr><td>{{ $roles->firstItem() + $loop->index }}</td><td class="fw-semibold">{{ $role->name }} @if($role->is_system)<span class="badge bg-light text-muted border">Sistem</span>@endif</td><td>@if($role->slug === 'superadmin')<span class="role-tag">Semua akses</span>@else<span class="small text-muted">{{ count($role->permissions ?? []) }} izin{{ empty($role->permissions) ? ' / Belum diatur' : '' }}</span>@endif</td><td>{{ $role->users_count }}</td><td><span class="badge {{ $role->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $role->is_active ? 'Aktif' : 'Non Aktif' }}</span></td><td class="text-end"><button class="btn btn-sm btn-outline-primary edit-role" data-id="{{ $role->id }}" @disabled($role->slug === 'superadmin')>{{ $role->slug === 'superadmin' ? 'Dilindungi' : 'Ubah' }}</button>@if(!$role->is_system)<button class="btn btn-sm btn-outline-danger delete-role" data-id="{{ $role->id }}">Hapus</button>@endif</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-5">Role tidak ditemukan.</td></tr>@endforelse</tbody></table></div><div class="card-footer bg-white">{{ $roles->links() }}</div></div>
<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form method="post" id="roleForm" class="modal-content border-0 shadow">@csrf<input type="hidden" name="_method" id="roleMethod" value="POST"><input type="hidden" name="form_role_id" id="formRoleId"><div class="modal-header role-modal-header"><div><h5 class="modal-title fw-bold" id="roleModalTitle">Buat Role</h5><div class="small opacity-75 mt-1">Pilih hak akses untuk modul CMS Digital Store.</div></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body p-4">
<div id="roleStepOne"><label class="form-label fw-semibold" for="roleName">Nama Role <span class="text-danger">*</span></label><input class="form-control mb-3" name="name" id="roleName" maxlength="100" required><input type="hidden" name="is_active" value="0"><div class="form-check form-switch mb-3"><input class="form-check-input" name="is_active" value="1" type="checkbox" id="roleActive" checked><label class="form-check-label" for="roleActive">Role Aktif</label></div><div class="role-permissions"><table class="table role-matrix mb-0"><thead><tr><th>Modul</th><th>Lihat</th><th>Edit / Tambah</th><th>Hapus</th><th>Export</th></tr></thead><tbody>
@foreach($modules as $key => $module)<tr data-module="{{ $key }}"><td class="fw-semibold small">{{ $module['label'] }}</td>@foreach(['view'=>'Lihat','edit'=>'Edit / Tambah','delete'=>'Hapus','export'=>'Export'] as $action => $label)<td>@if(in_array($action, $module['actions'], true))<input type="checkbox" name="permissions[]" value="{{ $key.'.'.$action }}" aria-label="{{ $module['label'].' - '.$label }}">@else<span class="text-muted" title="Tindakan ini tidak tersedia pada modul ini">-</span>@endif</td>@endforeach</tr>@endforeach
</tbody></table></div><p class="small text-muted mt-3 mb-0">Izin Edit, Hapus, atau Export otomatis menyertakan Lihat. User & Role serta token inbound PIM tetap khusus SuperAdmin. Role tanpa izin hanya dapat membuka profil sendiri.</p></div>
<div id="roleStepTwo" class="d-none"><h6 class="fw-bold">Periksa Role</h6><p id="roleReviewName" class="mb-2"></p><div id="roleReviewPermissions" class="border rounded-3 p-3 small"></div><p class="small text-muted mt-3">Perubahan izin berlaku pada request berikutnya, termasuk user yang sudah login.</p></div>
</div><div class="modal-footer role-modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-outline-secondary d-none" id="roleBack">Kembali</button><button type="button" class="btn btn-eiger" id="roleNext" disabled>Lanjut</button><button class="btn btn-eiger d-none" id="roleSave">Simpan Role</button></div></form></div></div>
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" id="deleteRoleForm" class="modal-content">@csrf @method('DELETE')<div class="modal-header"><h5 class="modal-title" id="deleteRoleTitle">Hapus Role</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body">Hapus role <strong id="deleteRoleName"></strong>? Role yang masih dipakai user tidak dapat dihapus.</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger">Hapus Role</button></div></form></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const roles = @json($roles->getCollection()->keyBy('id'));
    const form = document.getElementById('roleForm');
    const modal = document.getElementById('roleModal');
    const name = document.getElementById('roleName');
    const inputs = [...form.querySelectorAll('input[name="permissions[]"]')];
    const next = document.getElementById('roleNext');
    const base = @json(url('admin/roles'));
    let review = false;
    function step(second) { review = second; document.getElementById('roleStepOne').classList.toggle('d-none', second); document.getElementById('roleStepTwo').classList.toggle('d-none', !second); next.classList.toggle('d-none', second); document.getElementById('roleBack').classList.toggle('d-none', !second); document.getElementById('roleSave').classList.toggle('d-none', !second); }
    function open(role = null) {
        form.reset(); form.action = role ? `${base}/${role.id}` : base;
        document.getElementById('roleMethod').value = role ? 'PUT' : 'POST';
        document.getElementById('formRoleId').value = role?.id || '';
        document.getElementById('roleModalTitle').textContent = role ? 'Ubah Role' : 'Buat Role';
        name.value = role?.name || ''; document.getElementById('roleActive').checked = role ? !!role.is_active : true;
        inputs.forEach(input => input.checked = role?.permissions?.includes(input.value) || false);
        next.disabled = !name.value.trim(); step(false); bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    inputs.forEach(input => input.addEventListener('change', () => {
        const [module, action] = input.value.split('.');
        if (input.checked && action !== 'view') inputs.find(item => item.value === `${module}.view`).checked = true;
        if (!input.checked && action === 'view') inputs.filter(item => item.value.startsWith(module+'.')).forEach(item => item.checked = false);
    }));
    name.addEventListener('input', () => next.disabled = !name.value.trim());
    document.getElementById('createRoleButton').addEventListener('click', () => open());
    document.querySelectorAll('.edit-role').forEach(button => button.addEventListener('click', () => open(roles[button.dataset.id])));
    document.querySelectorAll('.delete-role').forEach(button => button.addEventListener('click', () => { const role = roles[button.dataset.id]; document.getElementById('deleteRoleName').textContent = role.name; document.getElementById('deleteRoleForm').action = `${base}/${role.id}`; bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteRoleModal')).show(); }));
    next.addEventListener('click', () => { if (!name.reportValidity()) return; document.getElementById('roleReviewName').textContent = name.value; const chosen = inputs.filter(input => input.checked).map(input => input.getAttribute('aria-label')); const summary = document.getElementById('roleReviewPermissions'); summary.replaceChildren(); if (!chosen.length) summary.textContent = 'Tanpa izin modul. User hanya dapat mengakses profil sendiri.'; chosen.forEach(label => { const line = document.createElement('div'); line.className = 'py-1'; line.textContent = label; summary.append(line); }); step(true); });
    document.getElementById('roleBack').addEventListener('click', () => step(false));
    form.addEventListener('submit', event => { if (!review) { event.preventDefault(); next.click(); } });
    @if($errors->any() && old('name'))
    open(@json($oldRole));
    @if(!old('form_role_id')) form.action = base; document.getElementById('roleMethod').value = 'POST'; @endif
    @endif
});
</script>
@endpush
