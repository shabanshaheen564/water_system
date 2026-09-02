<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldPassword123'),
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'current_password' => 'oldPassword123',
            'new_password' => 'newPassword123',
            'new_password_confirmation' => 'newPassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password changed successfully',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('newPassword123', $user->password));
        $this->assertFalse(Hash::check('oldPassword123', $user->password));
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldPassword123'),
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'current_password' => 'wrongPassword',
            'new_password' => 'newPassword123',
            'new_password_confirmation' => 'newPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Current password is incorrect.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('oldPassword123', $user->password));
    }

    public function test_new_password_confirmation_mismatch_fails(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldPassword123'),
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'current_password' => 'oldPassword123',
            'new_password' => 'newPassword123',
            'new_password_confirmation' => 'differentPassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('oldPassword123', $user->password));
    }

    public function test_new_password_cannot_equal_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldPassword123'),
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'current_password' => 'oldPassword123',
            'new_password' => 'oldPassword123',
            'new_password_confirmation' => 'oldPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['new_password']);
        $response->assertJson([
            'message' => 'The new password must be different from the current password.',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('oldPassword123', $user->password));
    }

    public function test_validation_errors(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldPassword123'),
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        // Missing current_password
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'new_password' => 'newPassword123',
            'new_password_confirmation' => 'newPassword123',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // Missing new_password
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'current_password' => 'oldPassword123',
            'new_password_confirmation' => 'newPassword123',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);

        // Missing confirmation
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'current_password' => 'oldPassword123',
            'new_password' => 'newPassword123',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password_confirmation']);

        // Too short new password
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'current_password' => 'oldPassword123',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_unauthenticated_user_receives_401(): void
    {
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'oldPassword123',
            'new_password' => 'newPassword123',
            'new_password_confirmation' => 'newPassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_sensitive_fields_not_returned(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldPassword123'),
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile/password', [
            'current_password' => 'oldPassword123',
            'new_password' => 'newPassword123',
            'new_password_confirmation' => 'newPassword123',
        ]);

        $response->assertStatus(200);

        $responseData = $response->json();

        $this->assertArrayNotHasKey('password', $responseData);
        $this->assertArrayNotHasKey('current_password', $responseData);
        $this->assertArrayNotHasKey('new_password', $responseData);
        $this->assertArrayNotHasKey('token', $responseData);
        $this->assertArrayNotHasKey('plain_text_token', $responseData);
        $this->assertEquals('Password changed successfully', $responseData['message']);
    }
}