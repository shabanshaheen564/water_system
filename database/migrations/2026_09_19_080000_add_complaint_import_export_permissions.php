<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'complaints.import',
            'complaints.export',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (['System Owner', 'Admin', 'Engineer'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }
    }

    public function down(): void
    {
        foreach (['System Owner', 'Admin', 'Engineer'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->revokePermissionTo(['complaints.import', 'complaints.export']);
            }
        }

        Permission::whereIn('name', ['complaints.import', 'complaints.export'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
