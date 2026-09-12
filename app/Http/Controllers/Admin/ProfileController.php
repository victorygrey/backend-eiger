<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the user profile and password change form.
     */
    public function index(): View
    {
        return view('admin.profile.index', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Update the user profile and optionally change password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'email'            => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'required_with:new_password|nullable|current_password',
            'new_password'     => 'nullable|string|min:6|confirmed',
        ], [
            'name.required'                 => 'Nama lengkap wajib diisi.',
            'email.required'                => 'Alamat email wajib diisi.',
            'email.unique'                  => 'Alamat email sudah digunakan akun lain.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
            'new_password.min'              => 'Kata sandi baru minimal 6 karakter.',
            'new_password.confirmed'        => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (!empty($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
        }

        $user->save();

        return redirect()->route('admin.profile.index')
            ->with('success', 'Profil dan kata sandi berhasil diperbarui.');
    }
}
