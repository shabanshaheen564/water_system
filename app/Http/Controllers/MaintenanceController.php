<?php

namespace App\Http\Controllers;

use App\Http\Requests\Maintenance\StoreMaintenanceRequest;
use App\Models\Dataset;
use App\Models\GisFeature;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceAccessService $access) {}

    public function datasets(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('maintenance.view'), 403);

        $datasets = $this->access->datasetsFor($request->user())
            ->get(['id', 'name', 'display_name', 'geometry_type', 'srid']);

        return response()->json(['data' => $datasets]);
    }

    public function features(Request $request, Dataset $dataset): JsonResponse
    {
        abort_unless($request->user()->can('maintenance.view'), 403);
        abort_unless($this->access->canAccessDataset($request->user(), $dataset), 403);

        $features = GisFeature::query()
            ->where('dataset_id', $dataset->id)
            ->with('datasetRecord:id,values,identifier_value')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 100));

        return response()->json([
            'data' => $features->getCollection()->map(fn (GisFeature $feature) => [
                'id' => $feature->id,
                'dataset_id' => $feature->dataset_id,
                'identifier' => $feature->datasetRecord?->identifier_value,
                'values' => $feature->datasetRecord?->values ?? [],
            ])->values(),
            'links' => [
                'first' => $features->url(1),
                'last' => $features->url($features->lastPage()),
                'prev' => $features->previousPageUrl(),
                'next' => $features->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $features->currentPage(),
                'last_page' => $features->lastPage(),
                'per_page' => $features->perPage(),
                'total' => $features->total(),
            ],
        ]);
    }

    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $feature = GisFeature::with('dataset')->findOrFail($validated['gis_feature_id']);

        abort_unless($this->access->canAccessFeature($request->user(), $feature), 403);

        $maintenance = DB::transaction(function () use ($validated, $request, $feature) {
            return MaintenanceRequest::create([
                'gis_feature_id' => $feature->id,
                'reported_by' => $request->user()->id,
                'priority' => $validated['priority'] ?? 'medium',
                'status' => 'new',
                'problem_description' => $validated['problem_description'],
                'fault_description' => $validated['fault_description'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        $maintenance->load([
            'gisFeature.dataset:id,name,display_name',
            'gisFeature.datasetRecord:id,identifier_value',
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
        ]);

        return response()->json($this->format($maintenance), 201);
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        abort_unless($request->user()->can('maintenance.view'), 403);

        $maintenanceRequest->load([
            'gisFeature.dataset:id,name,display_name',
            'gisFeature.datasetRecord:id,identifier_value,values',
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
            'jobs.technician:id,name,email',
        ]);

        if ($maintenanceRequest->gisFeature && !$this->access->canAccessFeature($request->user(), $maintenanceRequest->gisFeature)) {
            abort(403);
        }

        return response()->json($this->format($maintenanceRequest));
    }

    private function format(MaintenanceRequest $maintenance): array
    {
        return [
            'id' => $maintenance->id,
            'request_no' => $maintenance->request_no,
            'status' => $maintenance->status,
            'priority' => $maintenance->priority,
            'problem_description' => $maintenance->problem_description,
            'fault_description' => $maintenance->fault_description,
            'notes' => $maintenance->notes,
            'requested_at' => $maintenance->requested_at?->toISOString(),
            'completed_at' => $maintenance->completed_at?->toISOString(),
            'gis_feature' => $maintenance->gisFeature ? [
                'id' => $maintenance->gisFeature->id,
                'dataset' => [
                    'id' => $maintenance->gisFeature->dataset?->id,
                    'name' => $maintenance->gisFeature->dataset?->name,
                    'display_name' => $maintenance->gisFeature->dataset?->display_name,
                ],
                'identifier' => $maintenance->gisFeature->datasetRecord?->identifier_value,
            ] : null,
            'reported_by' => $maintenance->reportedBy ? [
                'id' => $maintenance->reportedBy->id,
                'name' => $maintenance->reportedBy->name,
                'email' => $maintenance->reportedBy->email,
            ] : null,
            'assigned_to' => $maintenance->assignedTo ? [
                'id' => $maintenance->assignedTo->id,
                'name' => $maintenance->assignedTo->name,
                'email' => $maintenance->assignedTo->email,
            ] : null,
            'jobs' => $maintenance->jobs->map(fn ($job) => [
                'id' => $job->id,
                'technician_id' => $job->technician_id,
                'technician_name' => $job->technician?->name,
                'started_at' => $job->started_at?->toISOString(),
                'completed_at' => $job->completed_at?->toISOString(),
                'result' => $job->result,
                'notes' => $job->notes,
            ])->values()->all(),
        ];
    }
}
