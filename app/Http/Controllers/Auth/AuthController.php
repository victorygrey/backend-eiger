<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
            return redirect()->route(Auth::user()->homeRoute());
        }

        return view('auth.login');
    }

    /**
     * Handle a login request to the application.
     */
    public function login(Request $request): RedirectResponse|Response
    {
        $this->ensureDefaultUsersExist();

        $request->validate([
            'email' => ['nullable', 'string', 'max:150'],
            'login' => ['nullable', 'string', 'max:150'],
            'username' => ['nullable', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:4096'],
        ]);
        $loginInput = trim((string) ($request->input('email') ?? $request->input('login') ?? $request->input('username')));
        $password = (string) $request->input('password');

        if ($loginInput === '' || $password === '') {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Username/Email dan kata sandi wajib diisi.']);
        }

        $remember = $request->boolean('remember');
        $loginLower = strtolower($loginInput);

        // Full emails are exact identifiers. Legacy aliases must resolve uniquely,
        // and SQL LIKE metacharacters in them must never expand to other accounts.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $loginLower);
        $candidates = User::whereRaw('LOWER(email) = ?', [$loginLower])
            ->when(! str_contains($loginLower, '@'), fn ($q) => $q
                ->orWhereRaw('LOWER(name) = ?', [$loginLower])
                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$escaped.'@%']))
            ->limit(2)->get();
        $user = $candidates->count() === 1 ? $candidates->first() : null;
        $accountKey = 'cms-login:account:'.hash('sha256', $user ? 'id:'.$user->id : $loginLower);
        $ipKey = 'cms-login:ip:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($accountKey, 5) || RateLimiter::tooManyAttempts($ipKey, 30)) {
            $retryAfter = max(RateLimiter::availableIn($accountKey), RateLimiter::availableIn($ipKey), 1);
            return response('Terlalu banyak percobaan login. Silakan coba lagi setelah '.$retryAfter.' detik.', 429)
                ->header('Retry-After', $retryAfter);
        }

        if ($user && Hash::check($password, $user->password)) {
            if (! $user->isActive() || $user->awaitingSetup()) {
                RateLimiter::hit($accountKey, 60);
                RateLimiter::hit($ipKey, 60);
                return back()
                    ->withInput($request->only('email', 'remember'))
                    ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Silakan hubungi SuperAdmin.']);
            }

            Auth::login($user, $remember);
            $user->update(['last_login_at' => now()]);
            $request->session()->regenerate();
            $request->session()->put('password_hash_web', $user->getAuthPassword());
            RateLimiter::clear($accountKey);

            $request->session()->forget('url.intended');

            // Do not redirect to a previously attempted module outside the new role.
            return redirect()->route($user->homeRoute());
        }

        RateLimiter::hit($accountKey, 60);
        RateLimiter::hit($ipKey, 60);
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
        if (! app()->environment('local', 'testing')) {
            return;
        }
        try {
            if (User::count() === 0) {
                Artisan::call('db:seed', [
                    '--class' => 'UserSeeder',
                    '--force' => true,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-seed default users skipped: '.$e->getMessage());
        }
    }
}
