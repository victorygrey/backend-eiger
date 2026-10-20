<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\CmsPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount('users')->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))->orderBy('name')->paginate(20)->withQueryString(),
            'modules' => CmsPermissions::modules(),
        ]);
    }

    private function data(Request $request, ?Role $role = null): array
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($role?->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(CmsPermissions::keys())],
            'is_active' => ['required', 'boolean'],
        ]);
        if (Role::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->when($role, fn ($query) => $query->whereKeyNot($role->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'Nama role sudah digunakan.']);
        }
        if ($role?->is_system && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'Role sistem harus tetap aktif.']);
        }
        $data['permissions'] = CmsPermissions::normalize($data['permissions'] ?? []);

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->data($request);
        Role::create($data + ['slug' => Str::limit(Str::slug($data['name']), 21, '').'-'.Str::lower(Str::random(8))]);

        return back()->with('success', 'Role berhasil dibuat. Hak akses diterapkan pada setiap request CMS.');
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->slug === 'superadmin', 403, 'Hak akses SuperAdmin dilindungi.');
        $role->update($this->data($request, $role));

        return back()->with('success', 'Role dan hak akses berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat dihapus.');
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Pindahkan user ke role lain sebelum menghapus role ini.']);
        }
        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }
}
