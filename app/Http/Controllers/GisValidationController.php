<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Services\GisValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GisValidationController extends Controller
{
    public function api(Dataset $dataset, GisValidationService $validator): JsonResponse
    {
        return response()->json($validator->validate($dataset));
    }

    public function web(Dataset $dataset, GisValidationService $validator): View
    {
        return view('datasets.validation', $validator->validate($dataset));
    }
}
