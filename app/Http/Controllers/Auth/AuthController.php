<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the application login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        $this->ensureDefaultUsersExist();

        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle a login request to the application.
     */
    public function login(Request $request): RedirectResponse
    {
        $this->ensureDefaultUsersExist();

        $loginInput = trim((string) ($request->input('email') ?? $request->input('login') ?? $request->input('username')));
        $password = (string) $request->input('password');

        if ($loginInput === '' || $password === '') {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Username/Email dan kata sandi wajib diisi.']);
        }

        $remember = $request->boolean('remember');
        $loginLower = strtolower($loginInput);

        // Find user by exact email (case-insensitive), or username/role, or prefix before @
        $user = User::whereRaw('LOWER(email) = ?', [$loginLower])
            ->orWhereRaw('LOWER(name) = ?', [$loginLower])
            ->orWhereRaw('LOWER(role) = ?', [$loginLower])
            ->orWhere('email', 'like', "{$loginLower}@%")
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            if (!$user->isActive()) {
                return back()
                    ->withInput($request->only('email', 'remember'))
                    ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Silakan hubungi SuperAdmin.']);
            }

            Auth::login($user, $remember);
            $user->update(['last_login_at' => now()]);
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => 'Kombinasi akun dan kata sandi tidak cocok. Silakan periksa kembali email/username dan kata sandi Anda.']);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Anda telah berhasil keluar dari sistem.');
    }

    /**
     * Ensure default users exist if user database is empty.
     */
    private function ensureDefaultUsersExist(): void
    {
        try {
            if (User::count() === 0) {
                Artisan::call('db:seed', [
                    '--class' => 'UserSeeder',
                    '--force' => true,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-seed default users skipped: ' . $e->getMessage());
        }
    }
}
