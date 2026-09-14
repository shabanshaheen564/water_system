<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_web_login_successful(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
            'last_login_at' => null,
        ]);
        $user->givePermissionTo('datasets.view');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // Should redirect to /gis (intended route)
        $response->assertRedirect(route('gis.index'));

        // Follow redirect and verify authenticated session
        $response = $this->followingRedirects()->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_web_login_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
            'last_login_at' => null,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        // Should redirect back with errors
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        // Session should not be authenticated
        $this->assertFalse(Auth::check());
    }

    public function test_web_login_inactive_user(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => false,
            'last_login_at' => null,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());
    }

    public function test_web_login_validation_email_required(): void
    {
        $response = $this->from('/login')->post('/login', [
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_web_login_validation_password_required(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('password');
    }

    public function test_web_login_validation_email_format(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_web_login_remember_me(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
            'last_login_at' => null,
        ]);
        $user->givePermissionTo('datasets.view');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => true,
        ]);

        $response->assertRedirect(route('gis.index'));
        $this->assertTrue(Auth::check());
    }

    public function test_api_login_still_works(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
            'last_login_at' => null,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'last_login_at',
                ],
            ]);

        $this->assertEquals('Login successful', $response->json('message'));
        $this->assertEquals('Bearer', $response->json('token_type'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_web_logout(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->givePermissionTo('datasets.view');

        // Login first
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        $this->assertTrue(Auth::check());

        // Logout
        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertFalse(Auth::check());
    }
}