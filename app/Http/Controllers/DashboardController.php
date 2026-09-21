<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Services\OperationalReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, OperationalReportService $reports): View
    {
        // GIS data indicators
        $totalDatasets = Dataset::where('is_active', true)->count();

        $totalRecords = DatasetRecord::whereHas('dataset', function ($query) {
            $query->where('is_active', true);
        })->count();

        $spatialDatasets = Dataset::where('is_active', true)
            ->where('is_spatial', true)
            ->count();

        $gisFeatures = GisFeature::whereHas('dataset', function ($query) {
            $query->where('is_active', true);
        })->count();

        $recentDatasets = Dataset::where('is_active', true)
            ->withCount(['records', 'gisFeatures as features_count'])
            ->latest()
            ->take(5)
            ->get();

        // Operational indicators for the main dashboard.
        $operationalSummary = $reports->summary($request);

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
            'systemStatus',
            'operationalSummary'
        ));
    }
}
