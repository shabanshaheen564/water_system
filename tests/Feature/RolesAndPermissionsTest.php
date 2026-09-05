<?php

namespace Tests\Feature;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_all_six_roles_exist(): void
    {
        $expectedRoles = [
            'System Owner',
            'Admin',
            'GIS Admin',
            'Engineer',
            'Field Worker',
            'Viewer',
        ];

        foreach ($expectedRoles as $roleName) {
            $this->assertTrue(
                Role::where('name', $roleName)->exists(),
                "Role '{$roleName}' should exist"
            );
        }

        $this->assertEquals(6, Role::count());
    }

    public function test_required_permissions_exist(): void
    {
        $requiredPermissions = [
            // Users
            'users.view', 'users.create', 'users.update', 'users.delete',
            // Roles & Permissions
            'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'permissions.view',
            // Complaints
            'complaints.view', 'complaints.create', 'complaints.update', 'complaints.delete', 'complaints.transition', 'complaints.convert_to_task',
            // Tasks
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.transition', 'tasks.update_status', 'tasks.view_updates', 'tasks.create_update',
            // GIS
            'gis.view', 'gis.layers.create', 'gis.layers.update', 'gis.layers.delete', 'gis.fields.view', 'gis.features.create', 'gis.features.update', 'gis.features.delete', 'gis.import',
            // Assets
            'assets.view', 'assets.create', 'assets.update', 'assets.delete',
            // Reports
            'reports.view', 'reports.export',
            // Audit Logs
            'audit_logs.view',
        ];

        foreach ($requiredPermissions as $permissionName) {
            $this->assertTrue(
                Permission::where('name', $permissionName)->exists(),
                "Permission '{$permissionName}' should exist"
            );
        }
    }

    public function test_system_owner_has_all_permissions(): void
    {
        $systemOwner = Role::where('name', 'System Owner')->first();
        $this->assertNotNull($systemOwner);

        $allPermissions = Permission::all();
        $systemOwnerPermissions = $systemOwner->permissions;

        $this->assertEquals(
            $allPermissions->count(),
            $systemOwnerPermissions->count(),
            'System Owner should have all permissions'
        );

        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $systemOwner->hasPermissionTo($permission->name),
                "System Owner should have permission '{$permission->name}'"
            );
        }
    }

    public function test_admin_has_expected_permissions(): void
    {
        $admin = Role::where('name', 'Admin')->first();
        $this->assertNotNull($admin);

        $adminPermissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'permissions.view',
            'complaints.view', 'complaints.create', 'complaints.update', 'complaints.delete', 'complaints.transition', 'complaints.convert_to_task',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.transition', 'tasks.update_status', 'tasks.view_updates', 'tasks.create_update',
            'gis.view', 'gis.layers.create', 'gis.layers.update', 'gis.layers.delete', 'gis.fields.view', 'gis.features.create', 'gis.features.update', 'gis.features.delete', 'gis.import',
            'assets.view', 'assets.create', 'assets.update', 'assets.delete',
            'reports.view', 'reports.export',
            'audit_logs.view',
        ];

        foreach ($adminPermissions as $permissionName) {
            $this->assertTrue(
                $admin->hasPermissionTo($permissionName),
                "Admin should have permission '{$permissionName}'"
            );
        }
    }

    public function test_gis_admin_does_not_have_user_management_permissions(): void
    {
        $gisAdmin = Role::where('name', 'GIS Admin')->first();
        $this->assertNotNull($gisAdmin);

        $this->assertFalse($gisAdmin->hasPermissionTo('users.view'));
        $this->assertFalse($gisAdmin->hasPermissionTo('users.create'));
        $this->assertFalse($gisAdmin->hasPermissionTo('roles.view'));
        $this->assertFalse($gisAdmin->hasPermissionTo('permissions.view'));
    }

    public function test_field_worker_has_task_update_permissions(): void
    {
        $fieldWorker = Role::where('name', 'Field Worker')->first();
        $this->assertNotNull($fieldWorker);

        $this->assertTrue($fieldWorker->hasPermissionTo('tasks.view'));
        $this->assertTrue($fieldWorker->hasPermissionTo('tasks.update'));
        $this->assertTrue($fieldWorker->hasPermissionTo('tasks.transition'));
        $this->assertTrue($fieldWorker->hasPermissionTo('tasks.update_status'));
        $this->assertTrue($fieldWorker->hasPermissionTo('tasks.view_updates'));
        $this->assertTrue($fieldWorker->hasPermissionTo('tasks.create_update'));

        // Should NOT have create/delete/assign
        $this->assertFalse($fieldWorker->hasPermissionTo('tasks.create'));
        $this->assertFalse($fieldWorker->hasPermissionTo('tasks.delete'));
        $this->assertFalse($fieldWorker->hasPermissionTo('tasks.assign'));
    }

    public function test_viewer_has_read_only_permissions(): void
    {
        $viewer = Role::where('name', 'Viewer')->first();
        $this->assertNotNull($viewer);

        $this->assertTrue($viewer->hasPermissionTo('complaints.view'));
        $this->assertTrue($viewer->hasPermissionTo('tasks.view'));
        $this->assertTrue($viewer->hasPermissionTo('assets.view'));
        $this->assertTrue($viewer->hasPermissionTo('gis.view'));
        $this->assertTrue($viewer->hasPermissionTo('reports.view'));

        // Should NOT have create/update/delete permissions
        $this->assertFalse($viewer->hasPermissionTo('complaints.create'));
        $this->assertFalse($viewer->hasPermissionTo('tasks.create'));
        $this->assertFalse($viewer->hasPermissionTo('assets.create'));
        $this->assertFalse($viewer->hasPermissionTo('gis.layers.create'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->assertEquals(6, Role::count());
        $this->assertEquals(44, Permission::count()); // Total permissions defined

        // Run again
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->assertEquals(6, Role::count(), 'Roles should not duplicate on re-seed');
        $this->assertEquals(44, Permission::count(), 'Permissions should not duplicate on re-seed');
    }
}