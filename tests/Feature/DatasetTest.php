<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatasetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected User $user;
    protected string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $permission = Permission::where('name', 'datasets.create')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'datasets.view')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'datasets.update')->first();
        $this->admin->givePermissionTo($permission);

        $this->adminToken = $this->admin->createToken('mobile-app')->plainTextToken;

        $this->user = User::factory()->create();
        $permission = Permission::where('name', 'datasets.view')->first();
        $this->user->givePermissionTo($permission);
        $this->userToken = $this->user->createToken('mobile-app')->plainTextToken;
    }

    // Authentication & Authorization Tests
    public function test_unauthenticated_user_cannot_list_datasets(): void
    {
        $response = $this->getJson('/api/datasets');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_dataset(): void
    {
        $response = $this->postJson('/api/datasets', [
            'name' => 'test_dataset',
            'display_name' => 'Test Dataset',
            'dataset_type' => 'official_layer',
        ]);
        $response->assertStatus(401);
    }

    public function test_user_without_view_permission_gets_403_on_list(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/datasets');

        $response->assertStatus(403);
    }

    public function test_user_without_create_permission_gets_403_on_create(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('name', 'datasets.view')->first();
        $user->givePermissionTo($permission);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/datasets', [
            'name' => 'test_dataset',
            'display_name' => 'Test Dataset',
            'dataset_type' => 'official_layer',
        ]);

        $response->assertStatus(403);
    }

    // CRUD Tests
    public function test_user_with_create_permission_can_create_dataset(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/datasets', [
            'name' => 'wells',
            'display_name' => 'Wells',
            'description' => 'Water wells dataset',
            'dataset_type' => 'official_layer',
            'source_name' => 'Municipality GIS',
            'source_format' => 'Shapefile',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'display_name',
                'description',
                'dataset_type',
                'source_name',
                'source_format',
                'is_active',
                'created_by',
                'fields',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals('wells', $response->json('name'));
        $this->assertEquals('Wells', $response->json('display_name'));
        $this->assertEquals('official_layer', $response->json('dataset_type'));
        $this->assertTrue($response->json('is_active'));
        $this->assertEquals($this->admin->id, $response->json('created_by.id'));

        $this->assertDatabaseHas('datasets', [
            'name' => 'wells',
            'display_name' => 'Wells',
        ]);
    }

    public function test_create_dataset_with_invalid_name_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/datasets', [
            'name' => 'invalid-name', // hyphens not allowed
            'display_name' => 'Test',
            'dataset_type' => 'official_layer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_dataset_with_invalid_type_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/datasets', [
            'name' => 'test',
            'display_name' => 'Test',
            'dataset_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dataset_type']);
    }

    public function test_can_list_datasets_with_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Dataset::create([
                'name' => "dataset_{$i}",
                'display_name' => "Dataset {$i}",
                'dataset_type' => 'official_layer',
                'created_by' => $this->admin->id,
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/datasets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_filter_datasets_by_type(): void
    {
        Dataset::create([
            'name' => 'official_1',
            'display_name' => 'Official 1',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);
        Dataset::create([
            'name' => 'additional_1',
            'display_name' => 'Additional 1',
            'dataset_type' => 'additional_table',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/datasets?dataset_type=additional_table');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('additional_table', $response->json('data.0.dataset_type'));
    }

    public function test_can_show_dataset(): void
    {
        $dataset = Dataset::create([
            'name' => 'test_dataset',
            'display_name' => 'Test Dataset',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'display_name',
                'description',
                'dataset_type',
                'source_name',
                'source_format',
                'is_active',
                'created_by',
                'fields',
                'created_at',
                'updated_at',
            ]);
    }

    public function test_show_dataset_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/datasets/999999');

        $response->assertStatus(404);
    }

    public function test_user_with_update_permission_can_update_dataset(): void
    {
        $dataset = Dataset::create([
            'name' => 'original_name',
            'display_name' => 'Original Name',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$dataset->id}", [
            'display_name' => 'Updated Name',
            'description' => 'Updated description',
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $response->json('display_name'));
        $this->assertEquals('Updated description', $response->json('description'));
        $this->assertFalse($response->json('is_active'));
        $this->assertEquals('original_name', $response->json('name')); // name unchanged
    }

    public function test_cannot_update_created_by(): void
    {
        $dataset = Dataset::create([
            'name' => 'test',
            'display_name' => 'Test',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $otherUser = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$dataset->id}", [
            'created_by' => $otherUser->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($this->admin->id, $response->json('created_by.id'));
    }

    // Validation Tests
    public function test_duplicate_name_gets_422(): void
    {
        Dataset::create([
            'name' => 'existing',
            'display_name' => 'Existing',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/datasets', [
            'name' => 'existing',
            'display_name' => 'New',
            'dataset_type' => 'official_layer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // Sensitive Fields Tests
    public function test_sensitive_fields_not_returned(): void
    {
        $dataset = Dataset::create([
            'name' => 'test',
            'display_name' => 'Test',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }
}