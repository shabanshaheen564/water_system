<?php

namespace App\Http\Controllers;

use App\Http\Requests\Maintenance\StoreAssetInspectionRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceJobRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceRequest;
use App\Models\AssetInspection;
use App\Models\Dataset;
use App\Models\GisFeature;
use App\Models\MaintenanceJob;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('maintenance.view'), 403);

        $accessibleDatasetIds = $this->access->datasetsFor($request->user())->pluck('id');

        $query = MaintenanceRequest::query()
            ->with([
                'gisFeature.dataset:id,name,display_name',
                'gisFeature.datasetRecord:id,identifier_value,values',
                'assignedTo:id,name,email',
                'jobs.technician:id,name,email',
                'inspections.inspectedBy:id,name',
            ])
            ->whereHas('gisFeature', fn ($q) => $q->whereIn('dataset_id', $accessibleDatasetIds))
            ->latest('requested_at');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'ILIKE', "%{$search}%")
                    ->orWhere('problem_description', 'ILIKE', "%{$search}%");
            });
        }

        foreach (['status', 'priority', 'assigned_to'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $requests = $query->paginate(min(max($request->integer('per_page', 50), 1), 100));

        return response()->json([
            'data' => $requests->getCollection()->map(fn (MaintenanceRequest $maintenance) => $this->format($maintenance))->values(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

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
                'geojson' => $feature->toGeoJsonFeature(),
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

    public function inspect(StoreAssetInspectionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $feature = GisFeature::with(['dataset', 'datasetRecord'])->findOrFail($validated['gis_feature_id']);
        abort_unless($this->access->canAccessFeature($request->user(), $feature), 403);

        $maintenance = DB::transaction(function () use ($validated, $request, $feature) {
            AssetInspection::create([
                'gis_feature_id' => $feature->id,
                'inspected_by' => $request->user()->id,
                'inspection_at' => $validated['inspection_at'] ?? now(),
                'result' => $validated['result'],
                'problem_description' => $validated['problem_description'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($validated['result'] !== 'problem') {
                return null;
            }

            return MaintenanceRequest::create([
                'gis_feature_id' => $feature->id,
                'reported_by' => $request->user()->id,
                'priority' => 'medium',
                'status' => 'new',
                'problem_description' => $validated['problem_description'],
                'fault_description' => null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        if ($maintenance) {
            $maintenance->load([
                'gisFeature.dataset:id,name,display_name',
                'gisFeature.datasetRecord:id,identifier_value,values',
                'reportedBy:id,name,email',
                'assignedTo:id,name,email',
                'jobs.technician:id,name,email',
                'inspections.inspectedBy:id,name',
            ]);

            return response()->json($this->format($maintenance), 201);
        }

        return response()->json(['inspection_recorded' => true, 'maintenance_created' => false], 201);
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
                'assigned_to' => $validated['assigned_to'] ?? null,
                'priority' => $validated['priority'] ?? 'medium',
                'status' => 'new',
                'problem_description' => $validated['problem_description'],
                'fault_description' => $validated['fault_description'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        $maintenance->load([
            'gisFeature.dataset:id,name,display_name',
            'gisFeature.datasetRecord:id,identifier_value,values',
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
        ]);

        return response()->json($this->format($maintenance), 201);
    }

    public function update(UpdateMaintenanceRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->ensureVisible($request, $maintenanceRequest);
        $validated = $request->validated();

        DB::transaction(function () use ($maintenanceRequest, $validated) {
            $status = $validated['status'] ?? null;
            unset($validated['status']);

            $oldAssigned = $maintenanceRequest->assigned_to;
            $maintenanceRequest->fill($validated);

            if ($status === 'assigned' && $maintenanceRequest->assigned_to === null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['assigned_to' => 'يجب إسناد الطلب قبل نقله إلى حالة مسند.']);
            }
            if ($status === 'cancelled' && blank($validated['cancellation_reason'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cancellation_reason' => 'سبب الإلغاء مطلوب.']);
            }

            if ($maintenanceRequest->assigned_to !== null) {
                if ($oldAssigned === null) {
                    $maintenanceRequest->assigned_at = now();
                }
                if ($status === null && $maintenanceRequest->status === 'new') {
                    $maintenanceRequest->status = 'assigned';
                }
            }

            if ($status !== null) {
                $maintenanceRequest->status = $status;
            }

            if ($maintenanceRequest->status === 'in_progress' && !$maintenanceRequest->started_at) {
                $maintenanceRequest->started_at = now();
            }
            if ($maintenanceRequest->status === 'waiting') {
                $maintenanceRequest->waiting_at = now();
            }
            if ($maintenanceRequest->status === 'completed') {
                $maintenanceRequest->completed_at ??= now();
            }
            if ($maintenanceRequest->status === 'cancelled') {
                $maintenanceRequest->cancelled_at ??= now();
            }

            $maintenanceRequest->save();
        });

        $maintenanceRequest->load([
            'gisFeature.dataset:id,name,display_name',
            'gisFeature.datasetRecord:id,identifier_value,values',
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
            'jobs.technician:id,name,email',
            'inspections.inspectedBy:id,name',
        ]);

        return response()->json($this->format($maintenanceRequest));
    }

    public function storeJob(StoreMaintenanceJobRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->ensureVisible($request, $maintenanceRequest);
        $validated = $request->validated();

        DB::transaction(function () use ($request, $maintenanceRequest, $validated) {
            MaintenanceJob::create([
                'maintenance_request_id' => $maintenanceRequest->id,
                'technician_id' => $validated['technician_id'] ?? $request->user()->id,
                'started_at' => $validated['started_at'] ?? now(),
                'completed_at' => $validated['completed_at'] ?? now(),
                'diagnosed_fault' => $validated['diagnosed_fault'] ?? null,
                'repair_action' => $validated['repair_action'] ?? null,
                'materials_used' => $validated['materials_used'] ?? null,
                'result' => $validated['result'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $updates = ['started_at' => $validated['started_at'] ?? ($maintenanceRequest->started_at ?? now())];

            if ($validated['result'] === 'repaired') {
                $updates += ['status' => 'completed', 'completed_at' => $validated['completed_at'] ?? now(), 'repair_result' => 'تم الإصلاح'];
            } elseif ($validated['result'] === 'not_repaired') {
                $updates += ['status' => 'not_repaired', 'repair_result' => 'لم يتم الإصلاح'];
            } else {
                $updates += ['status' => 'in_progress'];
            }

            $maintenanceRequest->update($updates);
        });

        $maintenanceRequest->load([
            'gisFeature.dataset:id,name,display_name',
            'gisFeature.datasetRecord:id,identifier_value,values',
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
            'jobs.technician:id,name,email',
            'inspections.inspectedBy:id,name',
        ]);

        return response()->json($this->format($maintenanceRequest));
    }

    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->ensureVisible($request, $maintenanceRequest);
        abort_unless($request->user()->can('maintenance.update'), 403);
        $validated = $request->validate(['cancellation_reason' => ['required', 'string', 'max:5000']]);

        $maintenanceRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        $maintenanceRequest->load([
            'gisFeature.dataset:id,name,display_name',
            'gisFeature.datasetRecord:id,identifier_value,values',
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
            'jobs.technician:id,name,email',
            'inspections.inspectedBy:id,name',
        ]);

        return response()->json($this->format($maintenanceRequest));
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
            'inspections.inspectedBy:id,name',
        ]);

        if ($maintenanceRequest->gisFeature && !$this->access->canAccessFeature($request->user(), $maintenanceRequest->gisFeature)) {
            abort(403);
        }

        return response()->json($this->format($maintenanceRequest));
    }

    private function ensureVisible(Request $request, MaintenanceRequest $maintenanceRequest): void
    {
        abort_unless($request->user()->can('maintenance.view'), 403);
        $maintenanceRequest->loadMissing('gisFeature.dataset');
        if ($maintenanceRequest->gisFeature) {
            abort_unless($this->access->canAccessFeature($request->user(), $maintenanceRequest->gisFeature), 403);
        }
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
            'assigned_at' => $maintenance->assigned_at?->toISOString(),
            'started_at' => $maintenance->started_at?->toISOString(),
            'waiting_at' => $maintenance->waiting_at?->toISOString(),
            'cancelled_at' => $maintenance->cancelled_at?->toISOString(),
            'cancellation_reason' => $maintenance->cancellation_reason,
            'gis_feature' => $maintenance->gisFeature ? [
                'id' => $maintenance->gisFeature->id,
                'dataset' => [
                    'id' => $maintenance->gisFeature->dataset?->id,
                    'name' => $maintenance->gisFeature->dataset?->name,
                    'display_name' => $maintenance->gisFeature->dataset?->display_name,
                ],
                'identifier' => $maintenance->gisFeature->datasetRecord?->identifier_value,
                'values' => $maintenance->gisFeature->datasetRecord?->values ?? [],
                'geojson' => $maintenance->gisFeature->toGeoJsonFeature(),
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
                'diagnosed_fault' => $job->diagnosed_fault,
                'repair_action' => $job->repair_action,
                'materials_used' => $job->materials_used,
                'notes' => $job->notes,
            ])->values()->all(),
            'inspections' => $maintenance->inspections->map(fn ($inspection) => [
                'id' => $inspection->id,
                'inspection_at' => $inspection->inspection_at?->toISOString(),
                'result' => $inspection->result,
                'problem_description' => $inspection->problem_description,
                'notes' => $inspection->notes,
                'inspected_by' => $inspection->inspectedBy?->name,
            ])->values()->all(),
        ];
    }
}
