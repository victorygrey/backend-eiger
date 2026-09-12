<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->guest(route('login'));
        }

        if (!$user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Silakan hubungi SuperAdmin.']);
        }

        if (empty($roles)) {
            return $next($request);
        }

        // SuperAdmin always has access to everything
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $userRole = strtolower((string) $user->role);
        $allowedRoles = array_map('strtolower', $roles);

        if (in_array($userRole, $allowedRoles, true)) {
            return $next($request);
        }

        abort(403, 'Akses Ditolak: Anda tidak memiliki wewenang untuk membuka halaman ini.');
    }
}
