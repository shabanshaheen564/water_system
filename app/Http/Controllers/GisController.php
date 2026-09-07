<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\GisFeature;
use Illuminate\View\View;

class GisController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $spatialDatasets = Dataset::where('is_spatial', true)
            ->where('is_active', true)
            ->withCount(['gisFeatures as features_count' => function ($query) {
                $query->where('dataset_id', \DB::raw('datasets.id'));
            }])
            ->orderBy('display_name')
            ->get();

        return view('gis.index', compact('spatialDatasets'));
    }
}