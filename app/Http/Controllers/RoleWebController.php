<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleWebController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('roles.index', [
            'roles' => $roles,
            'title' => __('Roles'),
        ]);
    }

    public function edit(Role $role): View
    {
        $this->ensureRoleIsEditable($role);

        return view('roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(),
            'title' => __('Edit Role Permissions'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->ensureRoleIsEditable($role);

        $validated = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissionIds = collect($validated['permissions'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if (auth()->user()->hasRole('System Owner')) {
            $permissions = Permission::whereIn('id', $permissionIds)->where('guard_name', 'web')->get();
        } else {
            $userPermissionIds = auth()->user()->getAllPermissions()->pluck('id')->toArray();
            $filteredIds = $permissionIds->filter(fn ($id) => in_array($id, $userPermissionIds))->values();
            $permissions = Permission::whereIn('id', $filteredIds)->where('guard_name', 'web')->get();
        }

        $role->syncPermissions($permissions);

        return redirect()->route('roles.index')
            ->with('success', __('Role permissions updated successfully.'));
    }

    private function ensureRoleIsEditable(Role $role): void
    {
        if ($role->name === 'System Owner') {
            abort(403, __('The System Owner role is protected and cannot be modified.'));
        }
    }
}
