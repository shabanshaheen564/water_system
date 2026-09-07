<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use Illuminate\View\View;

class DatasetsController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $datasets = Dataset::with(['fields'])
            ->orderBy('display_name')
            ->paginate(20);

        return view('datasets.index', compact('datasets'));
    }
}