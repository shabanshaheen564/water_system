<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::all();

        $data = $permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => $permission->created_at?->toISOString(),
                'updated_at' => $permission->updated_at?->toISOString(),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'created_at' => $permission->created_at?->toISOString(),
            'updated_at' => $permission->updated_at?->toISOString(),
        ]);
    }
}