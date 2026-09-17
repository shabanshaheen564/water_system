<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        $data = $roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name, 'guard_name' => $role->guard_name, 'created_at' => $role->created_at?->toISOString(), 'updated_at' => $role->updated_at?->toISOString(), 'permissions' => $role->permissions->map(fn ($permission) => ['id' => $permission->id, 'name' => $permission->name, 'guard_name' => $permission->guard_name])->values()]);
        return response()->json(['data' => $data]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');
        return response()->json(['id' => $role->id, 'name' => $role->name, 'guard_name' => $role->guard_name, 'created_at' => $role->created_at?->toISOString(), 'updated_at' => $role->updated_at?->toISOString(), 'permissions' => $role->permissions->map(fn ($permission) => ['id' => $permission->id, 'name' => $permission->name, 'guard_name' => $permission->guard_name])->values()]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
            if ($request->has('permissions')) {
                $this->ensurePermissionsGrantable($request->permissions);
                $role->syncPermissions($request->permissions);
            }
            $role->load('permissions');
            return response()->json(['id' => $role->id, 'name' => $role->name, 'guard_name' => $role->guard_name, 'created_at' => $role->created_at?->toISOString(), 'updated_at' => $role->updated_at?->toISOString(), 'permissions' => $role->permissions->map(fn ($permission) => ['id' => $permission->id, 'name' => $permission->name, 'guard_name' => $permission->guard_name])->values()], 201);
        });
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();
        if ($role->name === 'System Owner' && $request->has('permissions') && empty($validated['permissions'])) abort(422, 'System Owner role must retain at least one permission.');
        $this->ensureRoleManagementAccess($role);
        if ($role->name === 'System Owner' && $validated['name'] !== 'System Owner') abort(422, 'The System Owner role name cannot be changed.');
        $role->update(['name' => $validated['name']]);
        if ($request->has('permissions')) $this->syncPermissionsWithProtection($role, $validated['permissions']);
        $role->load('permissions');
        return response()->json(['id' => $role->id, 'name' => $role->name, 'guard_name' => $role->guard_name, 'created_at' => $role->created_at?->toISOString(), 'updated_at' => $role->updated_at?->toISOString(), 'permissions' => $role->permissions->map(fn ($permission) => ['id' => $permission->id, 'name' => $permission->name, 'guard_name' => $permission->guard_name])->values()]);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        if ($role->name === 'System Owner' && empty($request->permissions)) abort(422, 'System Owner role must retain at least one permission.');
        $this->ensureRoleManagementAccess($role);
        $this->syncPermissionsWithProtection($role, $request->permissions);
        $role->load('permissions');
        return response()->json(['id' => $role->id, 'name' => $role->name, 'guard_name' => $role->guard_name, 'created_at' => $role->created_at?->toISOString(), 'updated_at' => $role->updated_at?->toISOString(), 'permissions' => $role->permissions->map(fn ($permission) => ['id' => $permission->id, 'name' => $permission->name, 'guard_name' => $permission->guard_name])->values()]);
    }

    protected function syncPermissionsWithProtection(Role $role, array $permissions): void
    {
        if ($role->name === 'System Owner' && empty($permissions)) abort(422, 'System Owner role must retain at least one permission.');
        $this->ensurePermissionsGrantable($permissions);
        $role->syncPermissions($permissions);
    }

    protected function ensurePermissionsGrantable(array $permissions): void
    {
        $currentUser = request()->user();
        if ($currentUser->hasRole('System Owner')) return;

        $grantable = $currentUser->getAllPermissions()->pluck('name')->all();
        $requested = Permission::query()->whereIn('name', $permissions)->pluck('name')->all();
        $missing = array_values(array_diff($requested, $grantable));

        if ($missing !== [] || count($requested) !== count(array_unique($permissions))) {
            abort(403, 'You cannot grant permissions that you do not possess.');
        }
    }

    protected function ensureRoleManagementAccess(Role $role): void
    {
        $currentUser = request()->user();
        if ($role->name === 'System Owner' && ! $currentUser->hasRole('System Owner')) abort(403, 'Only the System Owner can modify the System Owner role.');
    }
}
