<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatasetWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected User $user;
    protected string $userToken;
    protected User $viewer;
    protected string $viewerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('datasets.view');
        $this->admin->givePermissionTo('datasets.create');
        $this->admin->givePermissionTo('datasets.update');
        $this->admin->givePermissionTo('datasets.delete');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('datasets.view');
        $this->userToken = $this->user->createToken('test')->plainTextToken;

        $this->viewer = User::factory()->create();
        $this->viewerToken = $this->viewer->createToken('test')->plainTextToken;
    }

    // Dataset Access Tests
    public function test_authorized_user_can_access_datasets_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/datasets');

        $response->assertStatus(200)
            ->assertViewIs('datasets.index')
            ->assertViewHas('datasets');
    }

    public function test_unauthenticated_user_redirected_from_datasets(): void
    {
        $response = $this->get('/datasets');
        $response->assertStatus(302); // Redirect to login
    }

    public function test_user_without_datasets_view_denied(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get('/datasets');
        $response->assertStatus(403);
    }

    // Dataset Creation Tests
    public function test_authorized_user_can_create_dataset(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/datasets', [
                'name' => 'test_dataset',
                'display_name' => 'Test Dataset',
                'description' => 'A test dataset',
                'dataset_type' => 'official_layer',
                'source_name' => 'Test Source',
                'source_format' => 'Shapefile',
                'is_active' => true,
                'is_spatial' => true,
                'geometry_type' => 'Point',
                'srid' => 4326,
            ]);

        $response->assertRedirect(route('datasets.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('datasets', [
            'name' => 'test_dataset',
            'display_name' => 'Test Dataset',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);
    }

    public function test_user_without_create_permission_cannot_create_dataset(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('datasets.view');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->actingAs($user)
            ->post('/datasets', [
                'name' => 'test_dataset',
                'display_name' => 'Test Dataset',
                'dataset_type' => 'official_layer',
                'is_spatial' => true,
                'geometry_type' => 'Point',
                'srid' => 4326,
            ]);

        $response->assertStatus(403);
    }

    public function test_spatial_dataset_requires_geometry_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/datasets', [
                'name' => 'test_spatial',
                'display_name' => 'Test Spatial',
                'dataset_type' => 'official_layer',
                'is_spatial' => true,
                'is_active' => true,
                // Missing geometry_type and srid
            ]);

        $response->assertSessionHasErrors(['geometry_type', 'srid']);
    }

    public function test_spatial_dataset_requires_srid(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/datasets', [
                'name' => 'test_spatial',
                'display_name' => 'Test Spatial',
                'dataset_type' => 'official_layer',
                'is_spatial' => true,
                'is_active' => true,
                'geometry_type' => 'Point',
                // Missing srid
            ]);

        $response->assertSessionHasErrors('srid');
    }

    public function test_non_spatial_dataset_does_not_require_spatial_config(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/datasets', [
                'name' => 'non_spatial_table',
                'display_name' => 'Non-Spatial Table',
                'dataset_type' => 'additional_table',
                'is_spatial' => false,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('datasets.index'));

        $this->assertDatabaseHas('datasets', [
            'name' => 'non_spatial_table',
            'is_spatial' => false,
            'geometry_type' => null,
            'srid' => null,
        ]);
    }

    public function test_invalid_geometry_type_rejected(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/datasets', [
                'name' => 'test_invalid',
                'display_name' => 'Invalid Geometry',
                'dataset_type' => 'official_layer',
                'is_spatial' => true,
                'is_active' => true,
                'geometry_type' => 'InvalidType',
                'srid' => 4326,
            ]);

        $response->assertSessionHasErrors('geometry_type');
    }

    public function test_valid_geometry_types_accepted(): void
    {
        $geometryTypes = ['Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'];

        foreach ($geometryTypes as $type) {
            $name = 'test_' . strtolower($type);
            $response = $this->actingAs($this->admin)
                ->post('/datasets', [
                    'name' => $name,
                    'display_name' => 'Test ' . $type,
                    'dataset_type' => 'official_layer',
                    'is_spatial' => true,
                    'is_active' => true,
                    'geometry_type' => $type,
                    'srid' => 4326,
                ]);

            $response->assertRedirect(route('datasets.index'));
            $this->assertDatabaseHas('datasets', [
                'name' => $name,
                'geometry_type' => $type,
            ]);
        }
    }

    // Dataset Update Tests
    public function test_authorized_user_can_edit_dataset(): void
    {
        $dataset = Dataset::create([
            'name' => 'original_name',
            'display_name' => 'Original Name',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put('/datasets/' . $dataset->id, [
                'display_name' => 'Updated Name',
                'description' => 'Updated description',
                'is_active' => false,
            ]);

        $response->assertRedirect(route('datasets.index'))
            ->assertSessionHas('success');

        $dataset->refresh();
        $this->assertEquals('Updated Name', $dataset->display_name);
        $this->assertEquals('Updated description', $dataset->description);
        $this->assertFalse($dataset->is_active);
        $this->assertEquals('original_name', $dataset->name); // Name should not change
    }

    public function test_user_without_update_permission_cannot_edit_dataset(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('datasets.view');
        $token = $user->createToken('test')->plainTextToken;

        $dataset = Dataset::create([
            'name' => 'test_edit',
            'display_name' => 'Test Edit',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($user)
            ->put('/datasets/' . $dataset->id, [
                'display_name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_dataset_identity_protected_during_update(): void
    {
        $dataset = Dataset::create([
            'name' => 'original_name',
            'display_name' => 'Original Name',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put('/datasets/' . $dataset->id, [
                'name' => 'changed_name', // Should not be changeable
            ]);

        $response->assertRedirect();
        $dataset->refresh();
        $this->assertEquals('original_name', $dataset->name); // Name unchanged
    }

    // Dynamic Fields Tests
    public function test_authorized_user_can_create_field(): void
    {
        $dataset = Dataset::create([
            'name' => 'test_fields',
            'display_name' => 'Test Fields',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/datasets/' . $dataset->id . '/fields', [
                'name' => 'well_id',
                'display_name' => 'Well ID',
                'data_type' => 'string',
                'is_required' => true,
                'is_unique' => true,
                'is_identifier' => true,
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('datasets.fields.index', $dataset))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('dataset_fields', [
            'dataset_id' => $dataset->id,
            'name' => 'well_id',
            'is_identifier' => true,
        ]);
    }

    public function test_user_without_create_permission_cannot_create_field(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('datasets.view');
        $token = $user->createToken('test')->plainTextToken;

        $dataset = Dataset::create([
            'name' => 'test_fields',
            'display_name' => 'Test Fields',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($user)
            ->post('/datasets/' . $dataset->id . '/fields', [
                'name' => 'well_id',
                'display_name' => 'Well ID',
                'data_type' => 'string',
            ]);

        $response->assertStatus(403);
    }

    public function test_duplicate_field_name_rejected_in_same_dataset(): void
    {
        $dataset = Dataset::create([
            'name' => 'test_dup',
            'display_name' => 'Test Duplicate',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        // Create first field
        $dataset->fields()->create([
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
        ]);

        // Try to create duplicate
        $response = $this->actingAs($this->admin)
            ->post('/datasets/' . $dataset->id . '/fields', [
                'name' => 'well_id', // Duplicate name
                'display_name' => 'Well ID 2',
                'data_type' => 'string',
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_same_field_name_allowed_in_different_datasets(): void
    {
        $dataset1 = Dataset::create([
            'name' => 'dataset1',
            'display_name' => 'Dataset 1',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $dataset2 = Dataset::create([
            'name' => 'dataset2',
            'display_name' => 'Dataset 2',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        // Create field in first dataset
        $dataset1->fields()->create([
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
        ]);

        // Create same field name in second dataset - should work
        $response = $this->actingAs($this->admin)
            ->post('/datasets/' . $dataset2->id . '/fields', [
                'name' => 'well_id',
                'display_name' => 'Well ID',
                'data_type' => 'string',
            ]);

        $response->assertRedirect(route('datasets.fields.index', $dataset2->id));
        $this->assertDatabaseHas('dataset_fields', [
            'dataset_id' => $dataset2->id,
            'name' => 'well_id',
        ]);
    }

    public function test_field_data_type_validation_works(): void
    {
        $dataset = Dataset::create([
            'name' => 'test_types',
            'display_name' => 'Test Types',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $validTypes = ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text'];

        foreach (['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text'] as $type) {
            $response = $this->actingAs($this->admin)
                ->post('/datasets/' . $dataset->id . '/fields', [
                    'name' => 'field_' . $type,
                    'display_name' => 'Field ' . $type,
                    'data_type' => $type,
                ]);

            $response->assertRedirect(route('datasets.fields.index', $dataset->id));
            $this->assertDatabaseHas('dataset_fields', [
                'dataset_id' => $dataset->id,
                'data_type' => $type,
            ]);
        }
    }

    public function test_invalid_data_type_rejected(): void
    {
        $dataset = Dataset::create([
            'name' => 'test_invalid',
            'display_name' => 'Test Invalid',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/datasets/' . $dataset->id . '/fields', [
                'name' => 'bad_type',
                'display_name' => 'Bad Type',
                'data_type' => 'invalid_type',
            ]);

        $response->assertSessionHasErrors('data_type');
    }

    public function test_field_update_by_authorized_user(): void
    {
        $dataset = Dataset::create([
            'name' => 'update_test',
            'display_name' => 'Update Test',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $field = $dataset->fields()->create([
            'name' => 'original_name',
            'display_name' => 'Original',
            'data_type' => 'string',
        ]);

        $response = $this->actingAs($this->admin)
            ->put('/datasets/' . $dataset->id . '/fields/' . $field->id, [
                'display_name' => 'Updated Name',
                'is_required' => true,
            ]);

        $response->assertRedirect(route('datasets.fields.index', $dataset->id));
        $field->refresh();
        $this->assertEquals('Updated Name', $field->display_name);
        $this->assertTrue($field->is_required);
    }

    public function test_user_without_update_permission_cannot_edit_field(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('datasets.view');
        $token = $user->createToken('test')->plainTextToken;

        $dataset = Dataset::create([
            'name' => 'edit_test',
            'display_name' => 'Edit Test',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $field = $dataset->fields()->create([
            'name' => 'test_field',
            'display_name' => 'Test Field',
            'data_type' => 'string',
        ]);

        $response = $this->actingAs($user)
            ->put('/datasets/' . $dataset->id . '/fields/' . $field->id, [
                'display_name' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    public function test_field_identifier_uniqueness_enforced(): void
    {
        $dataset = Dataset::create([
            'name' => 'id_test',
            'display_name' => 'Identifier Test',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        // Create first identifier field
        $dataset->fields()->create([
            'name' => 'id_1',
            'display_name' => 'ID 1',
            'data_type' => 'string',
            'is_identifier' => true,
        ]);

        // Try to create second identifier field
        $response = $this->actingAs($this->admin)
            ->post('/datasets/' . $dataset->id . '/fields', [
                'name' => 'id_2',
                'display_name' => 'ID 2',
                'data_type' => 'string',
                'is_identifier' => true,
            ]);

        $response->assertSessionHasErrors('is_identifier');
    }

    // Field Deletion / Data Integrity Tests
    public function test_field_deletion_allowed_when_no_records_exist(): void
    {
        $dataset = Dataset::create([
            'name' => 'delete_test',
            'display_name' => 'Delete Test',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $field = $dataset->fields()->create([
            'name' => 'deletable_field',
            'display_name' => 'Deletable Field',
            'data_type' => 'string',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete('/datasets/' . $dataset->id . '/fields/' . $field->id);

        $response->assertRedirect(route('datasets.fields.index', $dataset->id));
        $this->assertDatabaseMissing('dataset_fields', ['id' => $field->id]);
    }

    public function test_field_deletion_blocked_when_records_use_field(): void
    {
        $dataset = Dataset::create([
            'name' => 'blocked_delete',
            'display_name' => 'Blocked Delete',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $field = $dataset->fields()->create([
            'name' => 'used_field',
            'display_name' => 'Used Field',
            'data_type' => 'string',
        ]);

        // Create a record using this field
        DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['used_field' => 'some value'],
            'identifier_value' => 'ID-001',
            'created_by' => $this->admin->id,
        ]);

        // Attempt to delete field - should fail or handle gracefully
        $response = $this->actingAs($this->admin)
            ->delete('/datasets/' . $dataset->id . '/fields/' . $field->id);

        // The field deletion should either be blocked or handle gracefully
        // The important thing is that existing records are not corrupted
        $this->assertDatabaseHas('dataset_fields', ['id' => $field->id]);
    }

    public function test_field_routes_reject_a_field_from_another_dataset(): void
    {
        $firstDataset = Dataset::create([
            'name' => 'first_dataset',
            'display_name' => 'First Dataset',
            'dataset_type' => 'additional_table',
            'created_by' => $this->admin->id,
        ]);
        $secondDataset = Dataset::create([
            'name' => 'second_dataset',
            'display_name' => 'Second Dataset',
            'dataset_type' => 'additional_table',
            'created_by' => $this->admin->id,
        ]);
        $field = $secondDataset->fields()->create([
            'name' => 'private_field',
            'display_name' => 'Private Field',
            'data_type' => 'string',
        ]);

        $this->actingAs($this->admin)
            ->get("/datasets/{$firstDataset->id}/fields/{$field->id}/edit")
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->put("/datasets/{$firstDataset->id}/fields/{$field->id}", ['display_name' => 'Changed'])
            ->assertNotFound();
    }

    public function test_field_delete_requires_delete_permission(): void
    {
        $dataset = Dataset::create([
            'name' => 'delete_permission',
            'display_name' => 'Delete Permission',
            'dataset_type' => 'additional_table',
            'created_by' => $this->admin->id,
        ]);
        $field = $dataset->fields()->create([
            'name' => 'field_to_keep',
            'display_name' => 'Field To Keep',
            'data_type' => 'string',
        ]);

        $this->actingAs($this->user)
            ->delete("/datasets/{$dataset->id}/fields/{$field->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('dataset_fields', ['id' => $field->id]);
    }

    public function test_dataset_configuration_cannot_change_after_records_exist(): void
    {
        $dataset = Dataset::create([
            'name' => 'locked_configuration',
            'display_name' => 'Locked Configuration',
            'dataset_type' => 'additional_table',
            'created_by' => $this->admin->id,
        ]);
        DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => [],
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/datasets/{$dataset->id}", ['dataset_type' => 'official_layer'])
            ->assertSessionHasErrors('dataset');
        $this->assertSame('additional_table', $dataset->fresh()->dataset_type);
    }

    public function test_field_name_cannot_change_after_records_use_it(): void
    {
        $dataset = Dataset::create([
            'name' => 'locked_field',
            'display_name' => 'Locked Field',
            'dataset_type' => 'additional_table',
            'created_by' => $this->admin->id,
        ]);
        $field = $dataset->fields()->create([
            'name' => 'original_key',
            'display_name' => 'Original Key',
            'data_type' => 'string',
        ]);
        DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['original_key' => 'value'],
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/datasets/{$dataset->id}/fields/{$field->id}", ['name' => 'renamed_key'])
            ->assertSessionHasErrors('field');
        $this->assertSame('original_key', $field->fresh()->name);
    }

    // Ensure existing GIS functionality still works
    public function test_existing_gis_functionality_still_works(): void
    {
        $dataset = Dataset::create([
            'name' => 'gis_compat',
            'display_name' => 'GIS Compatibility',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['name' => 'Test Well'],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);

        $geojson = '{"type":"Point","coordinates":[34.5,31.5]}';
        \App\Models\GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => \Illuminate\Support\Facades\DB::selectOne(
                "SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) as geometry",
                [$geojson]
            )->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        // GIS page should still work
        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets');

        // API should still work
        $response = $this->actingAs($this->admin)
            ->getJson("/api/datasets/{$dataset->id}/features");
        $response->assertStatus(200);
    }
}
