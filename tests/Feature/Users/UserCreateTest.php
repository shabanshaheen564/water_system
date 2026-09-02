<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_user_with_users_create_can_create_user(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        $role = Role::where('name', 'Engineer')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'is_active' => true,
            'roles' => ['Engineer'],
        ]);

        $response->assertStatus(201)
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

        $this->assertEquals('New User', $response->json('name'));
        $this->assertEquals('newuser@example.com', $response->json('email'));
        $this->assertTrue($response->json('is_active'));
        $this->assertEquals('Engineer', $response->json('roles.0.name'));

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);

        $createdUser = User::where('email', 'newuser@example.com')->first();
        $this->assertTrue(Hash::check('Password123', $createdUser->password));
        $this->assertTrue($createdUser->hasRole('Engineer'));
    }

    public function test_user_exists_in_database_after_creation(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
    }

    public function test_password_is_hashed(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check('Password123', $user->password));
    }

    public function test_roles_are_assigned(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'roles' => ['Engineer', 'Viewer'],
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('Engineer'));
        $this->assertTrue($user->hasRole('Viewer'));
    }

    public function test_user_without_users_create_gets_403(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_duplicate_email_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        User::factory()->create(['email' => 'existing@example.com']);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_validation_errors_get_422(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        // Missing name
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid email
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test',
            'email' => 'invalid-email',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Short password
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_mismatch_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Different123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_sensitive_fields_not_returned(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201);

        $data = $response->json();

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertArrayNotHasKey('personal_access_tokens', $data);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('plain_text_token', $data);
    }

    public function test_user_created_without_roles(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201);
        $this->assertEquals([], $response->json('roles'));
    }
}