<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRelationship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatasetRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected Dataset $parentDataset;
    protected Dataset $childDataset;
    protected DatasetField $parentIdentifierField;
    protected DatasetField $childReferenceField;

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

        $this->parentDataset = Dataset::create([
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $this->childDataset = Dataset::create([
            'name' => 'water_quality',
            'display_name' => 'Water Quality',
            'dataset_type' => 'additional_table',
            'created_by' => $this->admin->id,
        ]);

        $this->parentIdentifierField = DatasetField::create([
            'dataset_id' => $this->parentDataset->id,
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
        ]);

        $this->childReferenceField = DatasetField::create([
            'dataset_id' => $this->childDataset->id,
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
            'is_required' => true,
        ]);
    }

    // Authentication & Authorization Tests
    public function test_unauthenticated_user_cannot_list_relationships(): void
    {
        $response = $this->getJson("/api/datasets/{$this->parentDataset->id}/relationships");
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_relationship(): void
    {
        $response = $this->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
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
        ])->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $response->assertStatus(403);
    }

    // CRUD Tests
    public function test_user_with_create_permission_can_create_relationship(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'parent_dataset_id',
                'child_dataset_id',
                'parent_field_id',
                'child_field_id',
                'relationship_type',
                'parent_dataset',
                'child_dataset',
                'parent_field',
                'child_field',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals($this->parentDataset->id, $response->json('parent_dataset_id'));
        $this->assertEquals($this->childDataset->id, $response->json('child_dataset_id'));
        $this->assertEquals('one_to_many', $response->json('relationship_type'));

        $this->assertDatabaseHas('dataset_relationships', [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
        ]);
    }

    public function test_create_relationship_invalid_parent_field_gets_422(): void
    {
        $otherParentField = DatasetField::create([
            'dataset_id' => $this->childDataset->id, // Wrong dataset
            'name' => 'other_field',
            'display_name' => 'Other Field',
            'data_type' => 'string',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $otherParentField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Parent field does not belong to parent dataset', $response->json('message'));
    }

    public function test_create_relationship_invalid_child_field_gets_422(): void
    {
        $otherChildField = DatasetField::create([
            'dataset_id' => $this->parentDataset->id, // Wrong dataset
            'name' => 'other_field',
            'display_name' => 'Other Field',
            'data_type' => 'string',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $otherChildField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Child field does not belong to child dataset', $response->json('message'));
    }

    public function test_create_relationship_parent_not_identifier_or_unique_gets_422(): void
    {
        $nonIdentifierField = DatasetField::create([
            'dataset_id' => $this->parentDataset->id,
            'name' => 'description',
            'display_name' => 'Description',
            'data_type' => 'text',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $nonIdentifierField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('must be an identifier or unique', $response->json('message'));
    }

    public function test_create_relationship_same_dataset_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->parentDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $this->parentIdentifierField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_list_relationships(): void
    {
        DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->parentDataset->id}/relationships");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_list_relationships_includes_both_parent_and_child(): void
    {
        // Parent relationship
        DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
        ]);

        // Child relationship (this dataset as child)
        $otherDataset = Dataset::create([
            'name' => 'other',
            'display_name' => 'Other',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $otherField = DatasetField::create([
            'dataset_id' => $otherDataset->id,
            'name' => 'id',
            'display_name' => 'ID',
            'data_type' => 'string',
            'is_identifier' => true,
            'is_unique' => true,
        ]);

        DatasetRelationship::create([
            'parent_dataset_id' => $otherDataset->id,
            'child_dataset_id' => $this->parentDataset->id,
            'parent_field_id' => $otherField->id,
            'child_field_id' => $this->parentIdentifierField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->parentDataset->id}/relationships");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}