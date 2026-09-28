<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
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
        foreach (['datasets.create', 'datasets.view', 'datasets.update', 'datasets.delete'] as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            $this->admin->givePermissionTo($permission);
        }

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

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/datasets/{$this->parentDataset->id}/relationships", [
                'parent_dataset_id' => $this->parentDataset->id,
                'child_dataset_id' => $this->childDataset->id,
                'parent_field_id' => $this->parentIdentifierField->id,
                'child_field_id' => $this->childReferenceField->id,
                'relationship_type' => 'one_to_many',
            ]);
        $response->assertStatus(403);
    }

    public function test_create_relationship_parent_not_identifier_or_unique_gets_422(): void
    {
        $nonIdentifierField = DatasetField::create([
            'dataset_id' => $this->parentDataset->id,
            'name' => 'description',
            'display_name' => 'Description',
            'data_type' => 'string',
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
}