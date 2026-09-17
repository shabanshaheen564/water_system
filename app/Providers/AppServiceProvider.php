<?php

namespace App\Providers;

use App\Models\Dataset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function ($request) {
            $email = strtolower((string) $request->input('email'));
            return Limit::perMinute(5)->by($email . '|' . $request->ip());
        });

        RateLimiter::for('api', function ($request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(120)->by((string) $key);
        });

        View::composer('layouts.app', function ($view) {
            $view->with('spatialDatasetsCount', Dataset::where('is_active', true)
                ->where('is_spatial', true)
                ->count());
        });
    }
}
