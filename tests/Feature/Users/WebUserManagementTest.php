<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_system_owner_can_assign_custom_permissions_to_an_account(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('System Owner');
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Engineer');
        $permission = Permission::where('name', 'gis.import')->firstOrFail();

        $response = $this->actingAs($owner)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'is_active' => true,
            'roles' => ['Engineer'],
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertTrue($target->fresh()->hasDirectPermission('gis.import'));
    }

    public function test_admin_can_assign_custom_permissions_to_an_ordinary_account(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Engineer');
        $permission = Permission::where('name', 'reports.export')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'is_active' => true,
            'roles' => ['Engineer'],
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertTrue($target->fresh()->hasDirectPermission('reports.export'));
    }

    public function test_admin_cannot_edit_system_owner_account(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('System Owner');

        $response = $this->actingAs($admin)->get(route('users.edit', $owner));

        $response->assertForbidden();
    }

    public function test_system_owner_cannot_be_deleted_from_web(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('System Owner');

        $response = $this->actingAs($owner)->delete(route('users.destroy', $owner));

        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_admin_cannot_delete_system_owner_from_web(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('System Owner');

        $response = $this->actingAs($admin)->delete(route('users.destroy', $owner));

        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_system_owner_can_delete_ordinary_user_from_web(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('System Owner');
        $target = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($owner)->delete(route('users.destroy', $target));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_escalate_permissions_via_user_creation(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $usersDeletePerm = Permission::where('name', 'users.delete')->first();
        $adminRole->syncPermissions($adminRole->permissions->where('name', '!=', 'users.delete')->pluck('id')->values()->toArray());

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');

        $permission = Permission::where('name', 'users.delete')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'is_active' => true,
            'roles' => ['Engineer'],
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertFalse(User::where('email', 'newuser@example.com')->first()->hasDirectPermission('users.delete'));
    }

    public function test_admin_cannot_escalate_permissions_via_user_update(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $adminRole->syncPermissions($adminRole->permissions->where('name', '!=', 'users.delete')->pluck('id')->values()->toArray());

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');

        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Engineer');
        $permission = Permission::where('name', 'users.delete')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'is_active' => true,
            'roles' => ['Engineer'],
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertFalse($target->fresh()->hasDirectPermission('users.delete'));
    }

    public function test_user_cannot_modify_own_roles_or_permissions(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('users.update');

        $permission = Permission::where('name', 'audit_logs.view')->firstOrFail();

        $response = $this->actingAs($user)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => true,
            'roles' => ['Engineer'],
            'permissions' => [$permission->id],
        ]);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasDirectPermission('audit_logs.view'));
        $this->assertFalse($user->fresh()->hasRole('Engineer'));
    }
}
