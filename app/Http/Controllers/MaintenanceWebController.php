<?php

namespace App\Http\Controllers;

use App\Http\Requests\Maintenance\StoreMaintenanceJobRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceRequest;
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

        $features = GisFeature::query()
            ->where('dataset_id', $dataset->id)
            ->with('datasetRecord:id,identifier_value')
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'dataset_record_id']);

        return response()->json(['data' => $features->map(fn (GisFeature $feature) => [
            'id' => $feature->id,
            'identifier' => $feature->datasetRecord?->identifier_value,
        ])->values()]);
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

        $maintenanceRequest->update($validated);
        if (($validated['assigned_to'] ?? null) !== null && $maintenanceRequest->status === 'new') {
            $maintenanceRequest->update(['status' => 'assigned']);
        }

        return redirect()->route('maintenance.show', $maintenanceRequest)->with('success', 'تم تحديث طلب الصيانة.');
    }

    public function storeJob(StoreMaintenanceJobRequest $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->ensureVisible($request, $maintenanceRequest);

        $validated = $request->validated();
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

        if ($validated['result'] === 'repaired') {
            $maintenanceRequest->update(['status' => 'completed', 'completed_at' => $validated['completed_at'] ?? now(), 'repair_result' => 'تم الإصلاح']);
        } elseif ($validated['result'] === 'not_repaired') {
            $maintenanceRequest->update(['status' => 'not_repaired', 'repair_result' => 'لم يتم الإصلاح']);
        } else {
            $maintenanceRequest->update(['status' => 'in_progress']);
        }

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

    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('maintenance.update'), 403);
        abort_unless($role->name !== 'System Owner', 403);

        $maintenancePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', 'maintenance.%')
            ->get();

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer'],
        ]);

        $selected = $maintenancePermissions
            ->whereIn('id', $validated['permissions'] ?? [])
            ->values();

        $existingNonMaintenance = $role->permissions()
            ->whereNotIn('permissions.id', $maintenancePermissions->pluck('id'))
            ->get();

        $role->syncPermissions($existingNonMaintenance->merge($selected));

        return back()->with('success', 'تم تحديث صلاحيات الصيانة للدور.');
    }

    private function ensureVisible(Request $request, MaintenanceRequest $maintenanceRequest): void
    {
        abort_unless($request->user()->can('maintenance.view'), 403);

        $maintenanceRequest->loadMissing('gisFeature.dataset');

        if ($maintenanceRequest->gisFeature) {
            abort_unless($this->access->canAccessFeature($request->user(), $maintenanceRequest->gisFeature), 403);
        }
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
