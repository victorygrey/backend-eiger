<?php

namespace App\Providers;

use App\Services\AtomMasterDataResolver;
use App\Services\MasterDataAttributeTranslator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Device endpoints may serialize dozens of products in one request.
        // Reuse the master-data snapshot so translation does not issue queries
        // for every custom attribute on every product.
        $this->app->scoped(AtomMasterDataResolver::class, fn () => new AtomMasterDataResolver);
        $this->app->scoped(
            MasterDataAttributeTranslator::class,
            fn ($app) => new MasterDataAttributeTranslator($app->make(AtomMasterDataResolver::class)),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            if ($appUrl = config('app.url')) {
                URL::forceRootUrl($appUrl);
            }

            return;
        }

        if ($host = request()->header('Host')) {
            $proto = request()->header('X-Forwarded-Proto', request()->isSecure() ? 'https' : 'http');
            URL::forceRootUrl("{$proto}://{$host}");
            if ($proto === 'https') {
                URL::forceScheme('https');
            }
        }
    }
}
