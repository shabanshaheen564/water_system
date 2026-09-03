<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        $data = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'created_at' => $role->created_at?->toISOString(),
                'updated_at' => $role->updated_at?->toISOString(),
                'permissions' => $role->permissions->map(fn($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ])->values(),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
            'permissions' => $role->permissions->map(fn($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ])->values(),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'web',
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            $role->load('permissions');

            return response()->json([
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'created_at' => $role->created_at?->toISOString(),
                'updated_at' => $role->updated_at?->toISOString(),
                'permissions' => $role->permissions->map(fn($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ])->values(),
            ], 201);
        });
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();

        $role->update([
            'name' => $validated['name'],
        ]);

        if ($request->has('permissions')) {
            $this->syncPermissionsWithProtection($role, $validated['permissions']);
        }

        $role->load('permissions');

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
            'permissions' => $role->permissions->map(fn($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ])->values(),
        ]);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $this->syncPermissionsWithProtection($role, $request->permissions);

        $role->load('permissions');

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
            'permissions' => $role->permissions->map(fn($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ])->values(),
        ]);
    }

    /**
     * Sync permissions with System Owner protection.
     * System Owner role cannot have its permissions removed.
     */
    protected function syncPermissionsWithProtection(Role $role, array $permissions): void
    {
        // System Owner role cannot have its permissions removed
        if ($role->name === 'System Owner' && empty($permissions)) {
            abort(422, 'System Owner role must retain at least one permission.');
        }

        $role->syncPermissions($permissions);
    }
}