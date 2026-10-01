<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Dataset;
use App\Models\WorkOrder;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class GisController extends Controller
{
    public function __construct(private readonly MaintenanceAccessService $maintenanceAccess) {}

    public function index(): View
    {
        $spatialDatasets = Dataset::query()->where('is_spatial', true)->where('is_active', true)->withCount(['gisFeatures as features_count'])->orderBy('map_order')->orderBy('display_name')->get();
        return view('gis.index', compact('spatialDatasets'));
    }

    public function operationalData(): JsonResponse
    {
        $user = request()->user();
        abort_unless($user->can('gis.view') || $user->can('complaints.view') || $user->can('tasks.view') || $user->can('datasets.view'), 403);
        $payload = ['complaints' => [], 'work_orders' => [], 'maintenance' => [], 'datasets' => [], 'permissions' => ['complaints' => $user->can('complaints.view'), 'tasks' => $user->can('tasks.view'), 'maintenance' => $user->can('maintenance.view'), 'datasets' => $user->can('datasets.view')]];

        if ($user->can('complaints.view')) {
            $payload['complaints'] = Complaint::query()->with(['assignedTo:id,name', 'workOrders:id,work_order_number,status'])->whereNotNull('latitude')->whereNotNull('longitude')->latest()->get()->map(fn (Complaint $complaint) => [
                'id' => $complaint->id, 'number' => $complaint->complaint_number, 'title' => $complaint->title, 'description' => $complaint->description, 'status' => $complaint->status, 'priority' => $complaint->priority, 'contact_name' => $complaint->contact_name, 'address' => $complaint->address, 'latitude' => (float) $complaint->latitude, 'longitude' => (float) $complaint->longitude, 'assigned_to' => $complaint->assignedTo?->name,
                'work_orders' => $complaint->workOrders->map(fn ($workOrder) => ['number' => $workOrder->work_order_number, 'status' => $workOrder->status])->values(), 'url' => route('complaints.show', $complaint),
            ])->values();
        }

        if ($user->can('tasks.view')) {
            $payload['work_orders'] = WorkOrder::query()->with(['assignedTo:id,name', 'complaints:id,latitude,longitude'])->latest()->get()->map(function (WorkOrder $workOrder) {
                $latitude = $workOrder->latitude !== null ? (float) $workOrder->latitude : null;
                $longitude = $workOrder->longitude !== null ? (float) $workOrder->longitude : null;
                if ($latitude === null || $longitude === null) {
                    $complaint = $workOrder->complaints->first(fn ($item) => $item->latitude !== null && $item->longitude !== null);
                    if ($complaint) { $latitude = (float) $complaint->latitude; $longitude = (float) $complaint->longitude; }
                }
                return [
                    'id' => $workOrder->id, 'number' => $workOrder->work_order_number, 'title' => $workOrder->title, 'description' => $workOrder->description, 'status' => $workOrder->status, 'priority' => $workOrder->priority, 'assigned_to' => $workOrder->assignedTo?->name,
                    'latitude' => $latitude, 'longitude' => $longitude, 'complaints_count' => $workOrder->complaints->count(), 'url' => route('work-orders.show', $workOrder),
                ];
            })->filter(fn (array $workOrder) => $workOrder['latitude'] !== null && $workOrder['longitude'] !== null)->values();
        }

        if ($user->can('maintenance.view')) {
            $maintenanceQuery = MaintenanceRequest::query()
                ->join('gis_features', 'gis_features.id', '=', 'maintenance_requests.gis_feature_id')
                ->with([
                    'gisFeature.dataset:id,display_name',
                    'gisFeature.datasetRecord:id,identifier_value,values',
                    'assignedTo:id,name',
                ])
                ->select('maintenance_requests.*')
                ->selectRaw("ST_Y(ST_PointOnSurface(ST_Transform(gis_features.geometry, 4326))) as latitude")
                ->selectRaw("ST_X(ST_PointOnSurface(ST_Transform(gis_features.geometry, 4326))) as longitude")
                ->whereNotNull('gis_features.geometry')
                ->latest('maintenance_requests.requested_at');

            if (!$this->maintenanceAccess->canManageAllRequests($user)) {
                $maintenanceQuery->where('maintenance_requests.assigned_to', $user->id);
            }

            $payload['maintenance'] = $maintenanceQuery->get()
                ->filter(fn (MaintenanceRequest $request) => $this->maintenanceAccess->canAccessRequest($user, $request))
                ->map(fn (MaintenanceRequest $request) => [
                    'id' => $request->id,
                    'number' => $request->request_no,
                    'title' => $request->problem_description,
                    'description' => $request->fault_description,
                    'status' => $request->status,
                    'priority' => $request->priority,
                    'assigned_to' => $request->assignedTo?->name,
                    'latitude' => (float) $request->latitude,
                    'longitude' => (float) $request->longitude,
                    'asset_name' => $request->gisFeature?->datasetRecord?->values['name_ar']
                        ?? $request->gisFeature?->datasetRecord?->values['name']
                        ?? $request->gisFeature?->datasetRecord?->identifier_value
                        ?? $request->gisFeature?->dataset?->display_name
                        ?? 'أصل جغرافي',
                    'url' => route('maintenance.show', $request),
                ])->values();
        }

        if ($user->can('datasets.view')) {
            $payload['datasets'] = Dataset::query()->where('is_spatial', true)->where('is_active', true)->select(['id', 'display_name', 'geometry_type', 'srid', 'is_spatial', 'management_mode', 'map_order', 'default_visible', 'map_opacity', 'display_color'])->withCount(['gisFeatures as features_count'])->orderBy('map_order')->orderBy('display_name')->get()->map(fn (Dataset $dataset) => [
                'id' => $dataset->id, 'name' => $dataset->display_name, 'geometry_type' => $dataset->geometry_type, 'srid' => $dataset->srid, 'management_mode' => $dataset->management_mode, 'features_count' => $dataset->features_count, 'map_order' => $dataset->map_order, 'default_visible' => $dataset->default_visible, 'map_opacity' => (float) $dataset->map_opacity, 'display_color' => $dataset->display_color,
            ])->values();
        }

        return response()->json($payload);
    }
}
