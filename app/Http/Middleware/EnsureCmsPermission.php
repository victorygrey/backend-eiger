<?php

namespace App\Http\Middleware;

use App\Support\CmsPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCmsPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->unsetRelation('accessRole');
        if (! $user?->isActive() || $user->awaitingSetup()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun atau role tidak aktif, atau aktivasi belum selesai.']);
        }
        $name = $request->route()?->getName();
        if ($name === 'admin.' || str_starts_with((string) $name, 'admin.profile.')) {
            return $next($request);
        }
        if ($user->isSuperAdmin()) {
            return $next($request);
        }
        $permission = CmsPermissions::forRoute($name, $request->method());
        abort_unless($permission && $user->hasPermission($permission), 403, 'Role Anda tidak memiliki izin untuk tindakan ini.');

        return $next($request);
    }
}
