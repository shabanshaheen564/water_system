<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use Illuminate\View\View;

class GisController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $spatialDatasets = Dataset::where('is_spatial', true)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return view('gis.index', compact('spatialDatasets'));
    }
}