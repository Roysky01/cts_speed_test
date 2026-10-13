<?php

namespace App\Providers;

use App\Actions\ResolveClientIp;
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
        $resolveClientIp = new ResolveClientIp;

        RateLimiter::for('speedtest', function (Request $request) use ($resolveClientIp) {
            return Limit::perMinute(600)->by($resolveClientIp($request));
        });

        RateLimiter::for('speedtest-results', function (Request $request) use ($resolveClientIp) {
            return Limit::perMinute(30)->by($resolveClientIp($request));
        });
    }
}
