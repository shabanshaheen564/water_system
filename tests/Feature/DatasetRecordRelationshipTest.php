<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\DatasetRelationship;
use App\Models\GisFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatasetRecordRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected Dataset $parentDataset;
    protected Dataset $childDataset;
    protected DatasetField $parentIdentifierField;
    protected DatasetField $childReferenceField;
    protected DatasetField $childNameField;
    protected DatasetRelationship $relationship;

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
        $permission = Permission::where('name', 'datasets.delete')->first();
        $this->admin->givePermissionTo($permission);

        $this->adminToken = $this->admin->createToken('mobile-app')->plainTextToken;

        // Parent dataset
        $this->parentDataset = Dataset::create([
            'name' => 'projects',
            'display_name' => 'Projects',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $this->parentIdentifierField = DatasetField::create([
            'dataset_id' => $this->parentDataset->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
        ]);

        // Child dataset
        $this->childDataset = Dataset::create([
            'name' => 'tasks',
            'display_name' => 'Tasks',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $this->childReferenceField = DatasetField::create([
            'dataset_id' => $this->childDataset->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => true,
        ]);

        $this->childNameField = DatasetField::create([
            'dataset_id' => $this->childDataset->id,
            'name' => 'task_name',
            'display_name' => 'Task Name',
            'data_type' => 'string',
        ]);

        // Relationship
        $this->relationship = DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
            'on_delete_behavior' => 'restrict',
            'is_nullable' => false,
        ]);
    }

    protected function createSpatialDataset(string $name, string $displayName, string $identifierFieldName): Dataset
    {
        $dataset = Dataset::create([
            'name' => $name,
            'display_name' => $displayName,
            'dataset_type' => 'official_layer',
            'management_mode' => 'web_editable',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        DatasetField::create([
            'dataset_id' => $dataset->id,
            'name' => $identifierFieldName,
            'display_name' => ucfirst($identifierFieldName),
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
        ]);

        return $dataset;
    }

    protected function createGisFeature(Dataset $dataset, DatasetRecord $record): GisFeature
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [0, 0],
            ],
        ]);

        $response->assertStatus(201);
        return GisFeature::where('dataset_record_id', $record->id)->firstOrFail();
    }

    // Children endpoint tests
    public function test_user_with_view_permission_can_list_children(): void
    {
        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'project_name' => 'Test Project'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 2'],
            'identifier_value' => 'TASK-002',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}/children");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals('TASK-001', $response->json('data.0.identifier_value'));
        $this->assertEquals('TASK-002', $response->json('data.1.identifier_value'));
    }

    public function test_children_endpoint_requires_view_permission(): void
    {
        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}/children");

        $response->assertStatus(403);
    }

    public function test_children_endpoint_returns_empty_when_no_children(): void
    {
        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}/children");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_children_endpoint_returns_404_for_nonexistent_parent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->parentDataset->id}/records/999999/children");

        $response->assertStatus(404);
    }

    public function test_children_endpoint_only_returns_children_for_correct_relationship(): void
    {
        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $otherParent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-002'],
            'identifier_value' => 'PROJ-002',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task for PROJ-001'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-002', 'task_name' => 'Task for PROJ-002'],
            'identifier_value' => 'TASK-002',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}/children");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('TASK-001', $response->json('data.0.identifier_value'));
    }

    // Parent endpoint tests
    public function test_user_with_view_permission_can_get_parent(): void
    {
        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'project_name' => 'Test Project'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $child = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->childDataset->id}/records/{$child->id}/parent");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $this->assertNotNull($response->json('data'));
        $this->assertEquals('PROJ-001', $response->json('data.identifier_value'));
        $this->assertEquals('parent', $response->json('data.relationship.direction'));
    }

    public function test_parent_endpoint_returns_null_when_no_parent_reference(): void
    {
        $child = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => null, 'task_name' => 'Orphan Task'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->childDataset->id}/records/{$child->id}/parent");

        $response->assertStatus(200);
        $this->assertNull($response->json('data'));
    }

    public function test_parent_endpoint_returns_404_for_nonexistent_child(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->childDataset->id}/records/999999/parent");

        $response->assertStatus(404);
    }

    // Cascade deletion tests
    public function test_cascade_delete_removes_child_records(): void
    {
        $this->relationship->update(['on_delete_behavior' => 'cascade']);

        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $child1 = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        $child2 = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 2'],
            'identifier_value' => 'TASK-002',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('dataset_records', ['id' => $parent->id]);
        $this->assertDatabaseMissing('dataset_records', ['id' => $child1->id]);
        $this->assertDatabaseMissing('dataset_records', ['id' => $child2->id]);
    }

    public function test_cascade_delete_fails_if_child_has_gis_feature(): void
    {
        $this->relationship->update(['on_delete_behavior' => 'cascade']);

        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $child = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        // Create a GIS feature for the child (requires spatial dataset)
        $spatialChildDataset = $this->createSpatialDataset('spatial_tasks', 'Spatial Tasks', 'task_id');

        $spatialReferenceField = DatasetField::create([
            'dataset_id' => $spatialChildDataset->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => true,
        ]);

        $spatialRelationship = DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $spatialChildDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $spatialReferenceField->id,
            'relationship_type' => 'one_to_many',
            'on_delete_behavior' => 'cascade',
            'is_nullable' => false,
        ]);

        $spatialChild = DatasetRecord::create([
            'dataset_id' => $spatialChildDataset->id,
            'values' => ['task_id' => 'STASK-001', 'project_id' => 'PROJ-001'],
            'identifier_value' => 'STASK-001',
            'created_by' => $this->admin->id,
        ]);

        // Create GIS feature for the spatial child
        $this->createGisFeature($spatialChildDataset, $spatialChild);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}");

        $response->assertStatus(409);
        $this->assertStringContainsString('GIS feature', $response->json('message'));

        // Parent should not be deleted
        $this->assertDatabaseHas('dataset_records', ['id' => $parent->id]);
        $this->assertDatabaseHas('dataset_records', ['id' => $spatialChild->id]);
    }

    public function test_cascade_delete_atomic_no_partial_deletes(): void
    {
        $this->relationship->update(['on_delete_behavior' => 'cascade']);

        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        // Create child without GIS feature (should be deletable)
        $child1 = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        // Create another child dataset with GIS feature constraint
        $spatialChildDataset = $this->createSpatialDataset('spatial_tasks2', 'Spatial Tasks 2', 'task_id');

        $spatialReferenceField = DatasetField::create([
            'dataset_id' => $spatialChildDataset->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => true,
        ]);

        $spatialRelationship = DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $spatialChildDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $spatialReferenceField->id,
            'relationship_type' => 'one_to_many',
            'on_delete_behavior' => 'cascade',
            'is_nullable' => false,
        ]);

        $spatialChild = DatasetRecord::create([
            'dataset_id' => $spatialChildDataset->id,
            'values' => ['task_id' => 'STASK-001', 'project_id' => 'PROJ-001'],
            'identifier_value' => 'STASK-001',
            'created_by' => $this->admin->id,
        ]);

        $this->createGisFeature($spatialChildDataset, $spatialChild);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}");

        $response->assertStatus(409);

        // Neither child should be deleted (atomic)
        $this->assertDatabaseHas('dataset_records', ['id' => $child1->id]);
        $this->assertDatabaseHas('dataset_records', ['id' => $spatialChild->id]);
        $this->assertDatabaseHas('dataset_records', ['id' => $parent->id]);
    }

    public function test_multiple_relationships_cascade_atomic(): void
    {
        // Create second child dataset
        $childDataset2 = Dataset::create([
            'name' => 'subtasks',
            'display_name' => 'Subtasks',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $childRefField2 = DatasetField::create([
            'dataset_id' => $childDataset2->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => true,
        ]);

        $childNameField2 = DatasetField::create([
            'dataset_id' => $childDataset2->id,
            'name' => 'subtask_name',
            'display_name' => 'Subtask Name',
            'data_type' => 'string',
        ]);

        DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $childDataset2->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $childRefField2->id,
            'relationship_type' => 'one_to_many',
            'on_delete_behavior' => 'cascade',
            'is_nullable' => false,
        ]);

        $this->relationship->update(['on_delete_behavior' => 'cascade']);

        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $child1 = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        $child2 = DatasetRecord::create([
            'dataset_id' => $childDataset2->id,
            'values' => ['project_id' => 'PROJ-001', 'subtask_name' => 'Subtask 1'],
            'identifier_value' => 'SUB-001',
            'created_by' => $this->admin->id,
        ]);

        // Add GIS feature to child2 to make cascade fail
        $spatialChildDataset2 = $this->createSpatialDataset('spatial_subtasks', 'Spatial Subtasks', 'subtask_id');

        $spatialProjectField = DatasetField::create([
            'dataset_id' => $spatialChildDataset2->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => true,
        ]);

        DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $spatialChildDataset2->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $spatialProjectField->id,
            'relationship_type' => 'one_to_many',
            'on_delete_behavior' => 'cascade',
            'is_nullable' => false,
        ]);

        $spatialChild = DatasetRecord::create([
            'dataset_id' => $spatialChildDataset2->id,
            'values' => ['subtask_id' => 'SSUB-001', 'project_id' => 'PROJ-001'],
            'identifier_value' => 'SSUB-001',
            'created_by' => $this->admin->id,
        ]);

        $this->createGisFeature($spatialChildDataset2, $spatialChild);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}");

        $response->assertStatus(409);

        // All children should remain
        $this->assertDatabaseHas('dataset_records', ['id' => $child1->id]);
        $this->assertDatabaseHas('dataset_records', ['id' => $child2->id]);
        $this->assertDatabaseHas('dataset_records', ['id' => $spatialChild->id]);
        $this->assertDatabaseHas('dataset_records', ['id' => $parent->id]);
    }

    // Set null tests
    public function test_set_null_on_delete_sets_child_reference_to_null(): void
    {
        // Create nullable child field
        $nullableChildDataset = Dataset::create([
            'name' => 'optional_tasks',
            'display_name' => 'Optional Tasks',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $nullableRefField = DatasetField::create([
            'dataset_id' => $nullableChildDataset->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => false, // nullable
        ]);

        $nullableNameField = DatasetField::create([
            'dataset_id' => $nullableChildDataset->id,
            'name' => 'task_name',
            'display_name' => 'Task Name',
            'data_type' => 'string',
        ]);

        $nullableRelationship = DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $nullableChildDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $nullableRefField->id,
            'relationship_type' => 'one_to_many',
            'on_delete_behavior' => 'set_null',
            'is_nullable' => true,
        ]);

        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $child = DatasetRecord::create([
            'dataset_id' => $nullableChildDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Optional Task'],
            'identifier_value' => 'OTASK-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}");

        $response->assertStatus(200);

        $child->refresh();
        $this->assertNull($child->values['project_id']);
        $this->assertDatabaseHas('dataset_records', ['id' => $child->id]);
        $this->assertDatabaseMissing('dataset_records', ['id' => $parent->id]);
    }

    public function test_set_null_rejected_when_relationship_not_nullable(): void
    {
        $this->relationship->update([
            'on_delete_behavior' => 'set_null',
            'is_nullable' => false,
        ]);

        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}");

        $response->assertStatus(409);
        $this->assertStringContainsString('not configured as nullable', $response->json('message'));
    }

    // Restrict behavior tests
    public function test_restrict_prevents_parent_deletion_when_children_exist(): void
    {
        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}");

        $response->assertStatus(409);
        $this->assertStringContainsString('dependent child records exist', $response->json('message'));

        $this->assertDatabaseHas('dataset_records', ['id' => $parent->id]);
    }

    // Transaction tests
    public function test_update_record_transaction_atomic_on_reference_validation_failure(): void
    {
        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $child = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        // Try to update child to reference non-existent parent
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$this->childDataset->id}/records/{$child->id}", [
            'values' => [
                'project_id' => 'NONEXISTENT',
            ],
        ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('Referenced parent record not found', $response->json('message'));

        // Child should not be updated
        $child->refresh();
        $this->assertEquals('PROJ-001', $child->values['project_id']);
    }

    public function test_destroy_record_transaction_atomic_on_cascade_failure(): void
    {
        $this->relationship->update(['on_delete_behavior' => 'cascade']);

        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $child = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        // Add GIS feature to parent to prevent deletion
        // Need to make parent dataset spatial first
        $this->parentDataset->update([
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $this->createGisFeature($this->parentDataset, $parent);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}");

        $response->assertStatus(409);

        // Child should not be deleted
        $this->assertDatabaseHas('dataset_records', ['id' => $child->id]);
        $this->assertDatabaseHas('dataset_records', ['id' => $parent->id]);
    }

    // Self-dataset relationship validation tests
    public function test_create_self_dataset_relationship_rejected(): void
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

    public function test_update_self_dataset_relationship_rejected_changing_parent(): void
    {
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

        $relationship = DatasetRelationship::create([
            'parent_dataset_id' => $otherDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $otherField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
        ]);

        // Try to update parent_dataset_id to childDataset->id (creating self-reference)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$otherDataset->id}/relationships/{$relationship->id}", [
            'parent_dataset_id' => $this->childDataset->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('must be different', $response->json('message'));
    }

    public function test_update_self_dataset_relationship_rejected_changing_child(): void
    {
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

        $relationship = DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $otherDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $otherField->id,
            'relationship_type' => 'one_to_many',
        ]);

        // Try to update child_dataset_id to parentDataset->id (creating self-reference)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$this->parentDataset->id}/relationships/{$relationship->id}", [
            'child_dataset_id' => $this->parentDataset->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('must be different', $response->json('message'));
    }

    // Set null accepted for nullable child field test
    public function test_set_null_accepted_for_nullable_child_field(): void
    {
        $nullableChildDataset = Dataset::create([
            'name' => 'nullable_tasks',
            'display_name' => 'Nullable Tasks',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $nullableRefField = DatasetField::create([
            'dataset_id' => $nullableChildDataset->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => false, // nullable
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $nullableChildDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $nullableRefField->id,
            'relationship_type' => 'one_to_many',
            'on_delete_behavior' => 'set_null',
            'is_nullable' => true,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('set_null', $response->json('on_delete_behavior'));
        $this->assertEquals(true, $response->json('is_nullable'));
    }

    // Children traversal authorization - verify child dataset access
    public function test_children_traversal_verifies_child_dataset_access(): void
    {
        // Create a child dataset that user doesn't have access to (inactive)
        $inactiveChildDataset = Dataset::create([
            'name' => 'inactive_tasks',
            'display_name' => 'Inactive Tasks',
            'dataset_type' => 'official_layer',
            'is_active' => false,
            'created_by' => $this->admin->id,
        ]);

        $inactiveRefField = DatasetField::create([
            'dataset_id' => $inactiveChildDataset->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => true,
        ]);

        DatasetRelationship::create([
            'parent_dataset_id' => $this->parentDataset->id,
            'child_dataset_id' => $inactiveChildDataset->id,
            'parent_field_id' => $this->parentIdentifierField->id,
            'child_field_id' => $inactiveRefField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $parent = DatasetRecord::create([
            'dataset_id' => $this->parentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $inactiveChildDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Inactive Task'],
            'identifier_value' => 'ITASK-001',
            'created_by' => $this->admin->id,
        ]);

        // Should return 404 because child dataset is inactive
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->parentDataset->id}/records/{$parent->id}/children");

        $response->assertStatus(404);
    }

    // Parent traversal authorization - verify parent dataset access
    public function test_parent_traversal_verifies_parent_dataset_access(): void
    {
        // Create an inactive parent dataset
        $inactiveParentDataset = Dataset::create([
            'name' => 'inactive_projects',
            'display_name' => 'Inactive Projects',
            'dataset_type' => 'official_layer',
            'is_active' => false,
            'created_by' => $this->admin->id,
        ]);

        $inactiveParentField = DatasetField::create([
            'dataset_id' => $inactiveParentDataset->id,
            'name' => 'project_id',
            'display_name' => 'Project ID',
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
        ]);

        DatasetRelationship::create([
            'parent_dataset_id' => $inactiveParentDataset->id,
            'child_dataset_id' => $this->childDataset->id,
            'parent_field_id' => $inactiveParentField->id,
            'child_field_id' => $this->childReferenceField->id,
            'relationship_type' => 'one_to_many',
        ]);

        $parent = DatasetRecord::create([
            'dataset_id' => $inactiveParentDataset->id,
            'values' => ['project_id' => 'PROJ-001'],
            'identifier_value' => 'PROJ-001',
            'created_by' => $this->admin->id,
        ]);

        $child = DatasetRecord::create([
            'dataset_id' => $this->childDataset->id,
            'values' => ['project_id' => 'PROJ-001', 'task_name' => 'Task 1'],
            'identifier_value' => 'TASK-001',
            'created_by' => $this->admin->id,
        ]);

        // Should return 404 because parent dataset is inactive
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->childDataset->id}/records/{$child->id}/parent");

        $response->assertStatus(404);
    }
}