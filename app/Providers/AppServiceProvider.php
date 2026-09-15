<?php

namespace App\Providers;

use App\Models\Dataset;
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
        View::composer('layouts.app', function ($view) {
            $view->with('spatialDatasetsCount', Dataset::where('is_active', true)
                ->where('is_spatial', true)
                ->count());
        });
    }
}
