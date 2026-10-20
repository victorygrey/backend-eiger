<?php

use App\Http\Middleware\EnsureCmsApiPermission;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->trimStrings(except: ['new_password', 'new_password_confirmation']);
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'cms-api' => EnsureCmsApiPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['new_password', 'new_password_confirmation', 'activation_code']);
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 419
                && request()->isMethod('post')
                && request()->is('login')) {
                return redirect()->route('login')->with(
                    'status',
                    'Sesi login telah kedaluwarsa. Silakan masukkan kembali akun Anda.'
                );
            }

            return $response;
        });
    })->create();
