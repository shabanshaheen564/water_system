<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'maintenance.view',
            'maintenance.create',
            'maintenance.update',
            'maintenance.assign',
            'maintenance.complete',
            'maintenance.inspect',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'System Owner' => $permissions,
            'Admin' => $permissions,
            'Engineer' => $permissions,
            'Field Worker' => [
                'maintenance.view',
                'maintenance.create',
                'maintenance.update',
                'maintenance.complete',
                'maintenance.inspect',
            ],
            'Viewer' => ['maintenance.view'],
        ];

        foreach ($rolePermissions as $roleName => $rolePermissionNames) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($rolePermissionNames);
            }
        }
    }

    public function down(): void
    {
        $permissions = Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'maintenance.view',
                'maintenance.create',
                'maintenance.update',
                'maintenance.assign',
                'maintenance.complete',
                'maintenance.inspect',
            ])
            ->get();

        foreach ($permissions as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};
