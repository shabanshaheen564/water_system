<?php

namespace Tests\Feature\Roles;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_system_owner_can_view_and_update_non_owner_role_permissions(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('System Owner');
        $role = Role::where('name', 'Engineer')->firstOrFail();
        $permission = Permission::where('name', 'audit_logs.view')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('roles.edit', $role))
            ->assertOk()
            ->assertSee(__('messages.permissions.audit_logs.view'));

        $response = $this->actingAs($owner)->put(route('roles.update', $role), [
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertTrue($role->fresh()->hasPermissionTo('audit_logs.view'));
    }

    public function test_admin_can_update_non_owner_role_permissions(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');
        $role = Role::where('name', 'Engineer')->firstOrFail();
        $permission = Permission::where('name', 'reports.export')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('roles.update', $role), [
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertTrue($role->fresh()->hasPermissionTo('reports.export'));
    }

    public function test_system_owner_role_is_protected_from_permission_changes(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('System Owner');
        $role = Role::where('name', 'System Owner')->firstOrFail();
        $permission = Permission::where('name', 'audit_logs.view')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('roles.edit', $role))
            ->assertForbidden();

        $this->actingAs($owner)
            ->put(route('roles.update', $role), ['permissions' => [$permission->id]])
            ->assertForbidden();
    }

    public function test_user_without_role_update_permission_cannot_update_role_permissions(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Viewer');
        $role = Role::where('name', 'Engineer')->firstOrFail();
        $permission = Permission::where('name', 'reports.export')->firstOrFail();

        $this->actingAs($user)
            ->get(route('roles.edit', $role))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('roles.update', $role), ['permissions' => [$permission->id]])
            ->assertForbidden();
    }

    public function test_permissions_page_is_available_to_admin_and_system_owner(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('System Owner');
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');

        $this->actingAs($owner)->get(route('permissions.index'))->assertOk();
        $this->actingAs($admin)->get(route('permissions.index'))->assertOk();
    }

    public function test_admin_cannot_escalate_role_permissions_to_unowned_permissions(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->givePermissionTo('roles.update');

        $role = Role::where('name', 'Engineer')->firstOrFail();
        $permission = Permission::where('name', 'users.delete')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('roles.update', $role), [
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertFalse($role->fresh()->hasPermissionTo('users.delete'));
    }
}
