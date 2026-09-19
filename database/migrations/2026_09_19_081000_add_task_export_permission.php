<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('tasks.export', 'web');

        foreach (['System Owner', 'Admin', 'Engineer', 'Field Worker'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) $role->givePermissionTo('tasks.export');
        }
    }

    public function down(): void
    {
        foreach (['System Owner', 'Admin', 'Engineer', 'Field Worker'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) $role->revokePermissionTo('tasks.export');
        }

        Permission::where('name', 'tasks.export')->where('guard_name', 'web')->delete();
    }
};
