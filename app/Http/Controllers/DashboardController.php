<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Total datasets
        $totalDatasets = Dataset::where('is_active', true)->count();

        // Total records across all datasets
        $totalRecords = DatasetRecord::whereHas('dataset', function ($query) {
            $query->where('is_active', true);
        })->count();

        // Spatial datasets
        $spatialDatasets = Dataset::where('is_active', true)
            ->where('is_spatial', true)
            ->count();

        // GIS features
        $gisFeatures = GisFeature::whereHas('dataset', function ($query) {
            $query->where('is_active', true);
        })->count();

        // Recent datasets (last 5)
        $recentDatasets = Dataset::where('is_active', true)
            ->withCount(['records', 'gisFeatures as features_count'])
            ->latest()
            ->take(5)
            ->get();

        // System status
        $systemStatus = [
            'database' => 'online',
            'api' => 'online',
            'gis' => $spatialDatasets > 0 ? 'available' : 'no_spatial_data',
        ];

        return view('dashboard.index', compact(
            'totalDatasets',
            'totalRecords',
            'spatialDatasets',
            'gisFeatures',
            'recentDatasets',
            'systemStatus'
        ));
    }
}