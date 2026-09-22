<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Models\User;
use App\Models\WorkOrder;
use App\Observers\AuditObserver;
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

            return Limit::perMinute((int) env('API_RATE_LIMIT', 120))->by((string) $key);
        });

        View::composer('layouts.app', function ($view) {
            $view->with('spatialDatasetsCount', Dataset::where('is_active', true)
                ->where('is_spatial', true)
                ->count());
        });

        foreach ([
            User::class,
            Dataset::class,
            DatasetRecord::class,
            GisFeature::class,
            Complaint::class,
            WorkOrder::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
