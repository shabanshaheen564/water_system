<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\GisFeature;
use App\Models\WorkOrder;
use App\Services\OperationalGisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalGisController extends Controller
{
    public function complaint(Complaint $complaint, OperationalGisService $gis): JsonResponse
    {
        return response()->json([
            'data' => $gis->complaintContext($complaint),
        ]);
    }

    public function workOrder(WorkOrder $workOrder, OperationalGisService $gis): JsonResponse
    {
        return response()->json([
            'data' => $gis->workOrderContext($workOrder),
        ]);
    }

    public function nearest(Request $request, OperationalGisService $gis): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'between:1,20'],
        ]);

        return response()->json([
            'data' => $gis->nearestAssets(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (int) ($validated['limit'] ?? 5)
            ),
        ]);
    }

    public function linkComplaint(Request $request, Complaint $complaint, GisFeature $feature, OperationalGisService $gis): JsonResponse
    {
        $gis->linkComplaint($complaint, $feature, $request->user()->id);

        return response()->json(['message' => 'GIS feature linked to complaint.']);
    }

    public function unlinkComplaint(Complaint $complaint, GisFeature $feature, OperationalGisService $gis): JsonResponse
    {
        $gis->unlinkComplaint($complaint, $feature);

        return response()->json(['message' => 'GIS feature unlinked from complaint.']);
    }

    public function linkWorkOrder(Request $request, WorkOrder $workOrder, GisFeature $feature, OperationalGisService $gis): JsonResponse
    {
        $gis->linkWorkOrder($workOrder, $feature, $request->user()->id);

        return response()->json(['message' => 'GIS feature linked to work order.']);
    }

    public function unlinkWorkOrder(WorkOrder $workOrder, GisFeature $feature, OperationalGisService $gis): JsonResponse
    {
        $gis->unlinkWorkOrder($workOrder, $feature);

        return response()->json(['message' => 'GIS feature unlinked from work order.']);
    }
}
