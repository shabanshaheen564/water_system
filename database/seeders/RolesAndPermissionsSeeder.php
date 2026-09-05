<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Roles & Permissions
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',

            // Complaints
            'complaints.view',
            'complaints.create',
            'complaints.update',
            'complaints.delete',
            'complaints.transition',
            'complaints.convert_to_task',

            // Tasks
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',
            'tasks.transition',
            'tasks.update_status',
            'tasks.view_updates',
            'tasks.create_update',

            // GIS
            'gis.view',
            'gis.layers.create',
            'gis.layers.update',
            'gis.layers.delete',
            'gis.fields.view',
            'gis.features.create',
            'gis.features.update',
            'gis.features.delete',
            'gis.import',

            // Assets
            'assets.view',
            'assets.create',
            'assets.update',
            'assets.delete',

            // Reports
            'reports.view',
            'reports.export',

            // Audit Logs
            'audit_logs.view',

            // Datasets
            'datasets.view',
            'datasets.create',
            'datasets.update',
            'datasets.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'System Owner' => $permissions,
            'Admin' => [
                'users.view', 'users.create', 'users.update', 'users.delete',
                'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'permissions.view',
                'complaints.view', 'complaints.create', 'complaints.update', 'complaints.delete', 'complaints.transition', 'complaints.convert_to_task',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.transition', 'tasks.update_status', 'tasks.view_updates', 'tasks.create_update',
                'gis.view', 'gis.layers.create', 'gis.layers.update', 'gis.layers.delete', 'gis.fields.view', 'gis.features.create', 'gis.features.update', 'gis.features.delete', 'gis.import',
                'assets.view', 'assets.create', 'assets.update', 'assets.delete',
                'reports.view', 'reports.export',
                'audit_logs.view',
                'datasets.view', 'datasets.create', 'datasets.update', 'datasets.delete',
            ],
            'GIS Admin' => [
                'gis.view', 'gis.layers.create', 'gis.layers.update', 'gis.layers.delete', 'gis.fields.view', 'gis.features.create', 'gis.features.update', 'gis.features.delete', 'gis.import',
                'assets.view', 'assets.create', 'assets.update',
                'reports.view', 'reports.export',
                'datasets.view', 'datasets.create', 'datasets.update', 'datasets.delete',
            ],
            'Engineer' => [
                'complaints.view', 'complaints.create', 'complaints.update', 'complaints.transition', 'complaints.convert_to_task',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.transition', 'tasks.update_status', 'tasks.view_updates', 'tasks.create_update',
                'assets.view',
                'reports.view', 'reports.export',
                'gis.view',
                'datasets.view', 'datasets.create', 'datasets.update',
            ],
            'Field Worker' => [
                'tasks.view', 'tasks.update', 'tasks.transition', 'tasks.update_status', 'tasks.view_updates', 'tasks.create_update',
                'assets.view',
                'gis.view',
                'datasets.view',
            ],
            'Viewer' => [
                'complaints.view',
                'tasks.view',
                'assets.view',
                'gis.view',
                'reports.view',
                'datasets.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($rolePermissions);
        }
    }
}