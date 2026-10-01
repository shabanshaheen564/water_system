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
use App\Models\User;
use App\Services\MaintenanceAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MaintenanceWebController extends Controller
{
    public function __construct(private readonly MaintenanceAccessService $access) {}

    public function index(Request $request): View
    {
        $datasets = $this->access->datasetsFor($request->user())->get(['id', 'display_name']);
        $accessibleDatasetIds = $datasets->pluck('id');

        $query = MaintenanceRequest::query()
            ->with([
                'gisFeature.dataset:id,name,display_name',
                'gisFeature.datasetRecord:id,identifier_value',
                'assignedTo:id,name',
            ])
            ->when(!$this->access->canManageAllRequests($request->user()), fn ($q) =>
                $q->where('assigned_to', $request->user()->id)
            )
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

        if ($request->filled('dataset_id')) {
            $datasetId = (int) $request->input('dataset_id');
            if (!$accessibleDatasetIds->contains($datasetId)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('gisFeature', fn ($q) => $q->where('dataset_id', $datasetId));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('requested_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('requested_at', '<=', $request->input('date_to'));
        }

        $requests = $query->paginate(20)->withQueryString();
        $technicians = $this->technicians();

        return view('maintenance.index', compact('requests', 'datasets', 'technicians'));
    }

    public function features(Request $request, Dataset $dataset): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()->can('maintenance.view'), 403);
        abort_unless($this->access->canAccessDataset($request->user(), $dataset), 403);

        $search = trim((string) $request->input('search'));
        $query = GisFeature::query()
            ->where('dataset_id', $dataset->id)
            ->with('datasetRecord:id,identifier_value,values')
            ->orderBy('id');

        if ($search !== '') {
            $query->whereHas('datasetRecord', function ($records) use ($search) {
                $records->where('identifier_value', 'ILIKE', "%{$search}%")
                    ->orWhereRaw("values::text ILIKE ?", ["%{$search}%"]);
            });
        }

        $features = $query->limit(500)->get();

        return response()->json([
            'data' => $features->map(fn (GisFeature $feature) => [
                'id' => $feature->id,
                'identifier' => $feature->datasetRecord?->identifier_value,
                'values' => $feature->datasetRecord?->values ?? [],
                'geojson' => $feature->toGeoJsonFeature(),
            ])->values(),
        ]);
    }

    public function inspect(StoreAssetInspectionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $feature = GisFeature::with(['dataset', 'datasetRecord'])->findOrFail($validated['gis_feature_id']);
        abort_unless($this->access->canAccessFeature($request->user(), $feature), 403);

        AssetInspection::create([
            'gis_feature_id' => $feature->id,
            'inspected_by' => $request->user()->id,
            'inspection_at' => $validated['inspection_at'] ?? now(),
            'result' => $validated['result'],
            'problem_description' => $validated['problem_description'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($validated['result'] === 'problem') {
            $maintenance = MaintenanceRequest::create([
                'gis_feature_id' => $feature->id,
                'reported_by' => $request->user()->id,
                'priority' => 'medium',
                'status' => 'new',
                'problem_description' => $validated['problem_description'],
                'fault_description' => null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()->route('maintenance.show', $maintenance)
                ->with('success', 'تم تسجيل الفحص وإنشاء طلب صيانة للمشكلة.');
        }

        return redirect()->route('maintenance.index')
            ->with('success', 'تم تسجيل الفحص بنجاح ولم يتم إنشاء طلب صيانة لأن الأصل سليم.');
    }

    public function create(Request $request): View
    {
        $datasets = $this->access->datasetsFor($request->user())->get(['id', 'display_name']);
        $technicians = $this->technicians();

        return view('maintenance.create', compact('datasets', 'technicians'));
    }

    public function store(StoreMaintenanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $feature = GisFeature::with(['dataset', 'datasetRecord'])->findOrFail($validated['gis_feature_id']);

        abort_unless($this->access->canAccessFeature($request->user(), $feature), 403);
        if (($validated['assigned_to'] ?? null) !== null) {
            abort_unless($this->access->canAssignRequests($request->user()), 403);
        }

        $maintenance = DB::transaction(fn () => MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $request->user()->id,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'new',
            'problem_description' => $validated['problem_description'],
            'fault_description' => $validated['fault_description'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]));

        return redirect()->route('maintenance.show', $maintenance)->with('success', 'تم إنشاء طلب الصيانة بنجاح.');
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): View
    {
        $this->ensureVisible($request, $maintenanceRequest);

        $maintenanceRequest->load([
            'gisFeature.dataset:id,name,display_name,maintenance_enabled',
            'gisFeature.datasetRecord:id,identifier_value,values',
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',
            'jobs.technician:id,name,email',
            'inspections.inspectedBy:id,name',
        ]);

        $technicians = $this->technicians();

        return view('maintenance.show', compact('maintenanceRequest', 'technicians'));
    }

    public function edit(Request $request, MaintenanceRequest $maintenanceRequest): View
    {
        $this->ensureVisible($request, $maintenanceRequest);
        abort_unless($request->user()->can('maintenance.update'), 403);

        $maintenanceRequest->load(['gisFeature.dataset:id,name,display_name', 'gisFeature.datasetRecord:id,identifier_value']);
        $technicians = $this->technicians();

        return view('maintenance.edit', compact('maintenanceRequest', 'technicians'));
    }

    public function update(UpdateMaintenanceRequest $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->ensureVisible($request, $maintenanceRequest);
        $validated = $request->validated();

        if (array_key_exists('assigned_to', $validated)) {
            $newAssignedTo = $validated['assigned_to'];
            if (!$this->access->canAssignRequests($request->user())
                && ((int) ($newAssignedTo ?? 0) !== (int) $request->user()->id)
            ) {
                abort(403);
            }
        }

        DB::transaction(function () use ($request, $maintenanceRequest, $validated) {
            $status = $validated['status'] ?? null;
            unset($validated['status']);

            if ($status !== null) {
                $this->access->assertStatusPermission($request->user(), $status);
                $this->access->assertStatusTransition($maintenanceRequest, $status);
            }

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

        return redirect()->route('maintenance.show', $maintenanceRequest)->with('success', 'تم تحديث طلب الصيانة.');
    }

    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->ensureVisible($request, $maintenanceRequest);
        abort_unless($this->access->canCancelRequests($request->user()), 403);
        $this->access->assertStatusTransition($maintenanceRequest, 'cancelled');
        $validated = $request->validate(['cancellation_reason' => ['required', 'string', 'max:5000']]);

        $maintenanceRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        return redirect()->route('maintenance.show', $maintenanceRequest)->with('success', 'تم إلغاء طلب الصيانة.');
    }

    public function storeJob(StoreMaintenanceJobRequest $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->ensureVisible($request, $maintenanceRequest);
        $validated = $request->validated();
        $this->access->assertJobExecutionAllowed($request->user(), $maintenanceRequest);

        if (!$this->access->canManageAllRequests($request->user())
            && array_key_exists('technician_id', $validated)
            && $validated['technician_id'] !== null
            && (int) $validated['technician_id'] !== (int) $request->user()->id
        ) {
            abort(403);
        }

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

        return redirect()->route('maintenance.show', $maintenanceRequest)->with('success', 'تم تسجيل تنفيذ الصيانة.');
    }

    public function settings(Request $request): View
    {
        abort_unless($request->user()->can('maintenance.update'), 403);

        $datasets = Dataset::query()
            ->where('is_spatial', true)
            ->with(['maintenanceRoles:id,name'])
            ->withCount('gisFeatures')
            ->orderBy('display_name')
            ->get();

        $roles = Role::query()->where('name', '!=', 'System Owner')->with('permissions')->orderBy('name')->get();
        $maintenancePermissions = Permission::query()->where('guard_name', 'web')->where('name', 'like', 'maintenance.%')->orderBy('name')->get();

        return view('maintenance.settings', compact('datasets', 'roles', 'maintenancePermissions'));
    }

    public function updateDatasetSettings(Request $request, Dataset $dataset): RedirectResponse
    {
        abort_unless($request->user()->can('maintenance.update'), 403);
        abort_unless($dataset->is_spatial, 422);

        $validated = $request->validate([
            'maintenance_enabled' => ['required', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $roleIds = Role::query()
            ->whereIn('id', $validated['role_ids'] ?? [])
            ->where('name', '!=', 'System Owner')
            ->pluck('id');

        DB::transaction(function () use ($dataset, $validated, $roleIds) {
            $dataset->update(['maintenance_enabled' => (bool) $validated['maintenance_enabled']]);
            $dataset->maintenanceRoles()->sync($roleIds);
        });

        return back()->with('success', 'تم تحديث إعدادات الصيانة للطبقة.');
    }

    public function updateRolePermissions(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('maintenance.update'), 403);

        $maintenancePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', 'maintenance.%')
            ->get();
        $maintenancePermissionIds = $maintenancePermissions->pluck('id');

        $validated = $request->validate([
            'role_permissions' => ['nullable', 'array'],
            'role_permissions.*' => ['nullable', 'array'],
            'role_permissions.*.*' => ['integer'],
        ]);

        DB::transaction(function () use ($validated, $maintenancePermissions, $maintenancePermissionIds) {
            $roles = Role::query()->where('name', '!=', 'System Owner')->get();

            foreach ($roles as $role) {
                $selected = $maintenancePermissions
                    ->whereIn('id', $validated['role_permissions'][$role->id] ?? [])
                    ->values();

                $existingNonMaintenance = $role->permissions()
                    ->whereNotIn('permissions.id', $maintenancePermissionIds)
                    ->get();

                $role->syncPermissions($existingNonMaintenance->merge($selected));
            }
        });

        return back()->with('success', 'تم حفظ جميع صلاحيات الصيانة بنجاح.');
    }

    private function ensureVisible(Request $request, MaintenanceRequest $maintenanceRequest): void
    {
        abort_unless($request->user()->can('maintenance.view'), 403);

        abort_unless($this->access->canAccessRequest($request->user(), $maintenanceRequest), 403);
    }

    private function technicians()
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Field Worker', 'Engineer', 'Admin', 'System Owner']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
