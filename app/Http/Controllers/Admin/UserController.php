<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of system users.
     */
    public function index(Request $request): View
    {
        $query = User::query();

        // Search filter (name or email)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        $stats = [
            'total'       => User::count(),
            'superadmins' => User::where('role', 'superadmin')->count(),
            'admins'      => User::where('role', 'admin')->count(),
            'active'      => User::where('is_active', true)->count(),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|max:150|unique:users,email',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:superadmin,admin',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required'     => 'Nama lengkap wajib diisi.',
            'email.required'    => 'Alamat email wajib diisi.',
            'email.unique'      => 'Alamat email ini sudah terdaftar.',
            'password.required' => 'Kata sandi awal wajib diisi.',
            'password.min'      => 'Kata sandi minimal 6 karakter.',
            'role.required'     => 'Peran pengguna wajib dipilih.',
        ]);

        User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'role'      => $validated['role'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Akun pengguna {$validated['name']} berhasil ditambahkan.");
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = Auth::user();

        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'password'  => 'nullable|string|min:6',
            'role'      => 'required|in:superadmin,admin',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required'  => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique'   => 'Alamat email ini sudah digunakan pengguna lain.',
            'password.min'   => 'Kata sandi baru minimal 6 karakter.',
            'role.required'  => 'Peran pengguna wajib dipilih.',
        ]);

        $isActive = $request->boolean('is_active');

        // Guard 1: Current user cannot demote or deactivate themselves
        if ($currentUser && $currentUser->id === $user->id) {
            if ($validated['role'] !== 'superadmin' && $currentUser->isSuperAdmin()) {
                return back()->withErrors(['role' => 'Anda tidak dapat menurunkan peran (role) akun Anda sendiri.']);
            }
            if (!$isActive) {
                return back()->withErrors(['is_active' => 'Anda tidak dapat menonaktifkan akun Anda sendiri saat sedang login.']);
            }
        }

        // Guard 2: Prevent demoting or deactivating the last active superadmin
        if ($user->isSuperAdmin() && ($validated['role'] !== 'superadmin' || !$isActive)) {
            $otherSuperAdmins = User::where('role', 'superadmin')
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->count();

            if ($otherSuperAdmins === 0) {
                return back()->withErrors(['role' => 'Sistem wajib memiliki minimal satu SuperAdmin aktif. Tidak dapat mengubah peran atau menonaktifkan akun ini.']);
            }
        }

        $data = [
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'is_active' => $isActive,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('success', "Informasi akun {$user->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $currentUser = Auth::user();

        // Guard 1: Cannot delete self
        if ($currentUser && $currentUser->id === $user->id) {
            return back()->withErrors(['error' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        // Guard 2: Cannot delete the last superadmin
        if ($user->isSuperAdmin()) {
            $remainingSuperAdmins = User::where('role', 'superadmin')
                ->where('id', '!=', $user->id)
                ->count();

            if ($remainingSuperAdmins === 0) {
                return back()->withErrors(['error' => 'Tidak dapat menghapus SuperAdmin terakhir di sistem.']);
            }
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "Akun pengguna {$name} berhasil dihapus dari sistem.");
    }
}
