<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatasetFieldTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected Dataset $dataset;

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

        $this->dataset = Dataset::create([
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);
    }

    // Authentication & Authorization Tests
    public function test_unauthenticated_user_cannot_list_fields(): void
    {
        $response = $this->getJson("/api/datasets/{$this->dataset->id}/fields");
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_field(): void
    {
        $response = $this->postJson("/api/datasets/{$this->dataset->id}/fields", [
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
        ]);
        $response->assertStatus(401);
    }

    public function test_user_without_create_permission_gets_403_on_create(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('name', 'datasets.view')->first();
        $user->givePermissionTo($permission);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/datasets/{$this->dataset->id}/fields", [
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
        ]);

        $response->assertStatus(403);
    }

    // CRUD Tests
    public function test_user_with_create_permission_can_create_field(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/fields", [
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
            'sort_order' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'dataset_id',
                'name',
                'display_name',
                'data_type',
                'is_required',
                'is_unique',
                'is_identifier',
                'default_value',
                'sort_order',
                'metadata',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals('well_id', $response->json('name'));
        $this->assertEquals('string', $response->json('data_type'));
        $this->assertTrue($response->json('is_required'));
        $this->assertTrue($response->json('is_unique'));
        $this->assertTrue($response->json('is_identifier'));

        $this->assertDatabaseHas('dataset_fields', [
            'dataset_id' => $this->dataset->id,
            'name' => 'well_id',
        ]);
    }

    public function test_create_field_with_invalid_data_type_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/fields", [
            'name' => 'test_field',
            'display_name' => 'Test Field',
            'data_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data_type']);
    }

    public function test_create_field_with_invalid_name_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/fields", [
            'name' => 'invalid-name',
            'display_name' => 'Test Field',
            'data_type' => 'string',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_list_fields(): void
    {
        DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'field_1',
            'display_name' => 'Field 1',
            'data_type' => 'string',
            'sort_order' => 1,
        ]);
        DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'field_2',
            'display_name' => 'Field 2',
            'data_type' => 'integer',
            'sort_order' => 2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->dataset->id}/fields");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_with_update_permission_can_update_field(): void
    {
        $field = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'original',
            'display_name' => 'Original',
            'data_type' => 'string',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/dataset-fields/{$field->id}", [
            'display_name' => 'Updated',
            'is_required' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated', $response->json('display_name'));
        $this->assertTrue($response->json('is_required'));
    }

    public function test_cannot_update_dataset_id(): void
    {
        $field = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'test',
            'display_name' => 'Test',
            'data_type' => 'string',
        ]);

        $otherDataset = Dataset::create([
            'name' => 'other',
            'display_name' => 'Other',
            'dataset_type' => 'additional_table',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/dataset-fields/{$field->id}", [
            'dataset_id' => $otherDataset->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($this->dataset->id, $response->json('dataset_id'));
    }

    // Identifier Field Tests
    public function test_cannot_create_multiple_identifier_fields(): void
    {
        DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'id_1',
            'display_name' => 'ID 1',
            'data_type' => 'string',
            'is_identifier' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/fields", [
            'name' => 'id_2',
            'display_name' => 'ID 2',
            'data_type' => 'string',
            'is_identifier' => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('identifier', $response->json('message'));
    }

    public function test_cannot_update_field_to_identifier_when_exists(): void
    {
        $field1 = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'id_1',
            'display_name' => 'ID 1',
            'data_type' => 'string',
            'is_identifier' => true,
        ]);

        $field2 = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'field_2',
            'display_name' => 'Field 2',
            'data_type' => 'string',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/dataset-fields/{$field2->id}", [
            'is_identifier' => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('identifier', $response->json('message'));
    }

    // Data Type Tests
    public function test_all_supported_data_types(): void
    {
        $types = ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text'];

        foreach ($types as $type) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->adminToken,
            ])->postJson("/api/datasets/{$this->dataset->id}/fields", [
                'name' => "field_{$type}",
                'display_name' => "Field {$type}",
                'data_type' => $type,
            ]);

            $response->assertStatus(201);
            $this->assertEquals($type, $response->json('data_type'));
        }
    }
}