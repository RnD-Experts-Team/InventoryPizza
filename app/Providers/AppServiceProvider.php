<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
    }

    /**
     * Read limiter for the bulk counts endpoint.
     *
     * This service had no rate limiting of any kind. That was tolerable while
     * every caller was another server; it is not once a browser calls it
     * directly — 44 store screens open at once, one of them stuck in a refresh
     * loop, and nothing here says stop. Each request also triggers a synchronous
     * token check against pizzasys, so the load lands on two services, not one.
     *
     * 120/minute per user is far above normal use — a screen makes one call per
     * day or week change — and still caps a runaway client.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('inventory-read', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
