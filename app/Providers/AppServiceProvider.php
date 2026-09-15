<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            if ($appUrl = config('app.url')) {
                \Illuminate\Support\Facades\URL::forceRootUrl($appUrl);
            }
            return;
        }

        if ($host = request()->header('Host')) {
            $proto = request()->header('X-Forwarded-Proto', request()->isSecure() ? 'https' : 'http');
            \Illuminate\Support\Facades\URL::forceRootUrl("{$proto}://{$host}");
            if ($proto === 'https') {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }
    }
}
