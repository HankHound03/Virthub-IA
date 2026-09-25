<?php

namespace App\Providers;

use App\Services\DatabaseUserStore;
use App\Services\JsonUserStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (!$this->app->environment('testing')) {
            $this->app->bind(JsonUserStore::class, DatabaseUserStore::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login-ip', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip() ?: 'unknown');
        });

        if (! app()->runningInConsole()) {
            $appUrl = (string) config('app.url');
            $request = request();

            if (
                str_starts_with($appUrl, 'https://')
                || $request->isSecure()
                || $request->headers->get('x-forwarded-proto') === 'https'
            ) {
                URL::forceScheme('https');
            }
        }
    }
}
