<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

class EnsureCmsApiPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        abort_unless($user, 401, 'Login CMS diperlukan untuk API pengelolaan.');
        $user->unsetRelation('accessRole');
        abort_unless($user->isActive() && ! $user->awaitingSetup(), 403, 'Akun atau role tidak aktif.');
        abort_unless($user->isSuperAdmin() || $user->hasPermission($permission), 403, 'Role Anda tidak memiliki izin untuk tindakan ini.');

        return app(AuthenticateSession::class)->handle($request, $next);
    }
}
