<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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
}
