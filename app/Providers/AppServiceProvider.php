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
        RateLimiter::for('pollution-reports', fn (Request $request) => Limit::perHour(5)
            ->by($request->ip())
            ->response(fn (Request $request, array $headers) => response()
                ->view('reports.rate-limited', [], 429, $headers)
                ->header('Cache-Control', 'no-store, private')));
    }
}
