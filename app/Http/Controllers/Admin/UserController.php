<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('accessRole')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%')->orWhere('job_title', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->boolean('status')))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::orderBy('name')->get()]);
    }

    private function data(Request $request, ?User $user = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name')), 'email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
            'job_title' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (User::whereRaw('LOWER(email) = ?', [$data['email']])->when($user, fn ($q) => $q->whereKeyNot($user->id))->exists()) {
            throw ValidationException::withMessages(['email' => 'Email sudah terdaftar.']);
        }
        $data['job_title'] = trim($data['job_title']);
        if ($data['job_title'] === '') {
            throw ValidationException::withMessages(['job_title' => 'Jabatan wajib diisi.']);
        }
        $role = Role::where('slug', $data['role'])->firstOrFail();
        if (! $role->is_active && $data['role'] !== $user?->role) {
            throw ValidationException::withMessages(['role' => 'Pilih role yang aktif.']);
        }
        $data['is_active'] = $request->boolean('is_active', $user?->is_active ?? true);

        return $data;
    }

    public function store(Request $request, UserInvitation $invitations)
    {
        $data = $this->data($request);
        $link = DB::transaction(function () use ($data, $invitations) {
            $user = User::create($data + ['password' => Str::random(64)]);

            return $invitations->issue($user);
        });

        return redirect()->route('admin.users.index')->with('success', 'User berhasil dibuat. Berikan tautan aktivasi untuk mengatur password.')->with('invitation_link', $link);
    }

    public function update(Request $request, User $user, UserInvitation $invitations)
    {
        $data = $this->data($request, $user);
        if ($request->user()->is($user)) {
            if ($data['role'] !== $user->role) {
                return back()->withInput()->withErrors(['role' => 'Anda tidak dapat mengubah role akun sendiri.']);
            }
            if (! $data['is_active']) {
                return back()->withInput()->withErrors(['is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
            }
        }
        $emailChanged = $data['email'] !== $user->email;
        DB::transaction(function () use ($user, $data) {
            $roots = User::whereIn('role', ['superadmin', 'superuser'])->where('is_active', true)->lockForUpdate()->get();
            if ($user->isSuperAdmin() && ($data['role'] !== 'superadmin' || ! $data['is_active']) && $roots->where('id', '!=', $user->id)->isEmpty()) {
                throw ValidationException::withMessages(['role' => 'Sistem wajib memiliki minimal satu SuperAdmin aktif.']);
            }
            $user->update($data);
        });
        $response = redirect()->route('admin.users.index')->with('success', 'Informasi user berhasil diperbarui.');
        if ($emailChanged && $user->awaitingSetup()) {
            $response->with('invitation_link', $invitations->issue($user));
        }

        return $response;
    }

    public function invitation(User $user, UserInvitation $invitations)
    {
        abort_unless($user->awaitingSetup() && $user->isActive(), 422, 'Tautan hanya tersedia untuk user aktif yang belum menyelesaikan aktivasi.');

        return back()->with('success', 'Tautan baru dibuat; tautan sebelumnya tidak berlaku.')->with('invitation_link', $invitations->issue($user));
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['error' => 'Anda tidak dapat menghapus akun sendiri.']);
        }
        DB::transaction(function () use ($user) {
            $roots = User::whereIn('role', ['superadmin', 'superuser'])->where('is_active', true)->lockForUpdate()->get();
            if ($user->isSuperAdmin() && $roots->where('id', '!=', $user->id)->isEmpty()) {
                throw ValidationException::withMessages(['error' => 'Tidak dapat menghapus SuperAdmin aktif terakhir.']);
            }
            $user->delete();
        });

        return back()->with('success', 'User berhasil dihapus.');
    }
}
