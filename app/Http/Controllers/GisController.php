<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Dataset;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class GisController extends Controller
{
    public function index(): View
    {
        $spatialDatasets = Dataset::query()
            ->where('is_spatial', true)
            ->where('is_active', true)
            ->withCount('gisFeatures')
            ->orderBy('display_name')
            ->get();

        return view('gis.index', compact('spatialDatasets'));
    }

    public function operationalData(): JsonResponse
    {
        $user = request()->user();
        abort_unless($user->can('gis.view') || $user->can('complaints.view') || $user->can('tasks.view') || $user->can('datasets.view'), 403);

        $payload = [
            'complaints' => [],
            'work_orders' => [],
            'datasets' => [],
            'permissions' => [
                'complaints' => $user->can('complaints.view'),
                'tasks' => $user->can('tasks.view'),
                'datasets' => $user->can('datasets.view'),
            ],
        ];

        if ($user->can('complaints.view')) {
            $payload['complaints'] = Complaint::query()
                ->with(['assignedTo:id,name', 'workOrders:id,work_order_number,status'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->latest()
                ->get()
                ->map(fn (Complaint $complaint) => [
                    'id' => $complaint->id,
                    'number' => $complaint->complaint_number,
                    'title' => $complaint->title,
                    'description' => $complaint->description,
                    'status' => $complaint->status,
                    'priority' => $complaint->priority,
                    'contact_name' => $complaint->contact_name,
                    'address' => $complaint->address,
                    'latitude' => (float) $complaint->latitude,
                    'longitude' => (float) $complaint->longitude,
                    'assigned_to' => $complaint->assignedTo?->name,
                    'work_orders' => $complaint->workOrders->map(fn ($workOrder) => [
                        'number' => $workOrder->work_order_number,
                        'status' => $workOrder->status,
                    ])->values(),
                    'url' => route('complaints.show', $complaint),
                ])->values();
        }

        if ($user->can('tasks.view')) {
            $payload['work_orders'] = WorkOrder::query()
                ->with(['assignedTo:id,name', 'complaints:id,latitude,longitude'])
                ->latest()
                ->get()
                ->map(function (WorkOrder $workOrder) {
                    $location = $workOrder->complaints->first(fn ($complaint) => $complaint->latitude !== null && $complaint->longitude !== null);

                    return [
                        'id' => $workOrder->id,
                        'number' => $workOrder->work_order_number,
                        'title' => $workOrder->title,
                        'description' => $workOrder->description,
                        'status' => $workOrder->status,
                        'priority' => $workOrder->priority,
                        'assigned_to' => $workOrder->assignedTo?->name,
                        'latitude' => $location ? (float) $location->latitude : null,
                        'longitude' => $location ? (float) $location->longitude : null,
                        'complaints_count' => $workOrder->complaints->count(),
                        'url' => route('work-orders.show', $workOrder),
                    ];
                })
                ->filter(fn (array $workOrder) => $workOrder['latitude'] !== null && $workOrder['longitude'] !== null)
                ->values();
        }

        if ($user->can('datasets.view')) {
            $payload['datasets'] = Dataset::query()
                ->where('is_spatial', true)
                ->where('is_active', true)
                ->withCount('gisFeatures')
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'geometry_type', 'srid', 'is_spatial'])
                ->map(fn (Dataset $dataset) => [
                    'id' => $dataset->id,
                    'name' => $dataset->display_name,
                    'geometry_type' => $dataset->geometry_type,
                    'srid' => $dataset->srid,
                    'features_count' => $dataset->gis_features_count,
                ])->values();
        }

        return response()->json($payload);
    }
}
