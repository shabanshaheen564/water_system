<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_authenticated_user_with_users_update_can_sync_roles(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();
        $engineerRole = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $viewerRole = \Spatie\Permission\Models\Role::where('name', 'Viewer')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => ['Engineer', 'Viewer'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'is_active',
                'last_login_at',
                'created_at',
                'updated_at',
                'roles',
            ]);

        $this->assertEquals('Engineer', $response->json('roles.0.name'));
        $this->assertEquals('Viewer', $response->json('roles.1.name'));

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('Engineer'));
        $this->assertTrue($targetUser->hasRole('Viewer'));
    }

    public function test_user_without_users_update_cannot_sync_roles(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => ['Engineer'],
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401_on_sync_roles(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => ['Engineer'],
        ]);

        $response->assertStatus(401);
    }

    public function test_nonexistent_user_returns_404_on_sync_roles(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/users/999999/roles', [
            'roles' => ['Engineer'],
        ]);

        $response->assertStatus(404);
    }

    public function test_invalid_role_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => ['NonExistentRole'],
        ]);

$response->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }

    public function test_empty_roles_array_removes_all_roles(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $engineerRole = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($engineerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => [],
        ]);

        $response->assertStatus(200);
        $this->assertEquals([], $response->json('roles'));

        $targetUser->refresh();
        $this->assertFalse($targetUser->hasRole('Engineer'));
    }

    public function test_roles_are_synced_correctly(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $engineerRole = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $viewerRole = \Spatie\Permission\Models\Role::where('name', 'Viewer')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($viewerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => ['Engineer'],
        ]);

        $response->assertStatus(200);

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('Engineer'));
        $this->assertFalse($targetUser->hasRole('Viewer'));
        $this->assertEquals('Engineer', $response->json('roles.0.name'));
    }

public function test_multiple_roles_can_be_assigned(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $engineerRole = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $viewerRole = \Spatie\Permission\Models\Role::where('name', 'Viewer')->first();
        $adminRole = Role::where('name', 'Admin')->first();
        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => ['Engineer', 'Viewer', 'Admin'],
        ]);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('roles'));

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('Engineer'));
        $this->assertTrue($targetUser->hasRole('Viewer'));
        $this->assertTrue($targetUser->hasRole('Admin'));
    }

    public function test_roles_must_be_array(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => 'not-an-array',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => ['Engineer'],
        ]);

        $response->assertStatus(401);
    }

    public function test_nonexistent_user_returns_404(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/users/999999/roles', [
            'roles' => ['Engineer'],
        ]);

        $response->assertStatus(404);
    }

    public function test_sensitive_fields_not_returned(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();
        $engineerRole = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $targetUser->assignRole($engineerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/roles", [
            'roles' => ['Engineer'],
        ]);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertArrayNotHasKey('personal_access_tokens', $data);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('plain_text_token', $data);
    }
}