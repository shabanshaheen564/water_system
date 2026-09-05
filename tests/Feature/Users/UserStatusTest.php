<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_authorized_user_can_deactivate_user(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'is_active' => true,
        ]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
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

        $this->assertEquals('Target User', $response->json('name'));
        $this->assertEquals('target@example.com', $response->json('email'));
        $this->assertFalse($response->json('is_active'));
        $this->assertNull($response->json('last_login_at'));
        $this->assertNotNull($response->json('created_at'));
        $this->assertNotNull($response->json('updated_at'));

        $targetUser->refresh();
        $this->assertFalse($targetUser->is_active);
    }

    public function test_authorized_user_can_activate_user(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => true,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('is_active'));

        $targetUser->refresh();
        $this->assertTrue($targetUser->is_active);
    }

    public function test_status_false_is_persisted(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create(['is_active' => true]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $targetUser->refresh();
        $this->assertFalse($targetUser->is_active);
    }

    public function test_status_true_is_persisted(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create(['is_active' => false]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => true,
        ]);

        $targetUser->refresh();
        $this->assertTrue($targetUser->is_active);
    }

    public function test_missing_is_active_returns_422(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_invalid_is_active_string_returns_422(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => 'not_a_boolean',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_integer_one_is_valid_boolean(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('is_active'));

        $targetUser->refresh();
        $this->assertTrue($targetUser->is_active);
    }

    public function test_integer_zero_is_valid_boolean(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create(['is_active' => true]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => 0,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('is_active'));

        $targetUser->refresh();
        $this->assertFalse($targetUser->is_active);
    }

    public function test_nonexistent_user_returns_404(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/users/999999/status', [
            'is_active' => false,
        ]);

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_update_status(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_users_update_permission_cannot_update_status(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_deactivation_does_not_change_password(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'password' => bcrypt('originalPassword123'),
        ]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $targetUser->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('originalPassword123', $targetUser->password));
    }

    public function test_deactivation_does_not_remove_roles(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $engineerRole = Role::where('name', 'Engineer')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($engineerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('Engineer'));
    }

    public function test_deactivation_does_not_change_name_email(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $targetUser->refresh();
        $this->assertEquals('Original Name', $targetUser->name);
        $this->assertEquals('original@example.com', $targetUser->email);
    }

    public function test_sensitive_fields_not_returned(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);

        $userData = $response->json();

        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);
        $this->assertArrayNotHasKey('personal_access_tokens', $userData);
        $this->assertArrayNotHasKey('token', $userData);
        $this->assertArrayNotHasKey('plain_text_token', $userData);
    }

    public function test_cannot_deactivate_last_active_system_owner(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $systemOwnerRole = Role::where('name', 'System Owner')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($systemOwnerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot deactivate the last active System Owner.',
            ]);

        $targetUser->refresh();
        $this->assertTrue($targetUser->is_active);
    }

    public function test_can_deactivate_system_owner_when_another_active_exists(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $systemOwnerRole = Role::where('name', 'System Owner')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($systemOwnerRole);

        // Create another active System Owner
        $anotherSystemOwner = User::factory()->create();
        $anotherSystemOwner->assignRole($systemOwnerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('is_active'));

        $targetUser->refresh();
        $this->assertFalse($targetUser->is_active);
    }
}