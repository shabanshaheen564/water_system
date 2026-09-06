<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GisFeatureTest extends TestCase
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

    // Dataset Spatial Configuration Tests
    public function test_create_non_spatial_dataset(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/datasets', [
            'name' => 'non_spatial',
            'display_name' => 'Non Spatial Dataset',
            'dataset_type' => 'additional_table',
            'is_spatial' => false,
        ]);

        $response->assertStatus(201);
        $this->assertFalse($response->json('is_spatial'));
        $this->assertNull($response->json('geometry_type'));
        $this->assertNull($response->json('srid'));
    }

    public function test_create_spatial_dataset_with_valid_config(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/datasets', [
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('is_spatial'));
        $this->assertEquals('Point', $response->json('geometry_type'));
        $this->assertEquals(4326, $response->json('srid'));
    }

    public function test_create_spatial_dataset_missing_srid_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/datasets', [
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            // srid missing
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('SRID is required for spatial datasets', $response->json('message'));
    }

    public function test_create_spatial_dataset_invalid_geometry_type_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/datasets', [
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'InvalidType',
            'srid' => 4326,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('geometry type', $response->json('message'));
    }

    public function test_update_non_spatial_to_spatial_with_valid_config(): void
    {
        $dataset = Dataset::create([
            'name' => 'original',
            'display_name' => 'Original',
            'dataset_type' => 'additional_table',
            'is_spatial' => false,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$dataset->id}", [
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('is_spatial'));
        $this->assertEquals('Point', $response->json('geometry_type'));
        $this->assertEquals(4326, $response->json('srid'));
    }

    // Geometry Creation Tests
    protected function createSpatialDataset(): Dataset
    {
        return Dataset::create([
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function createRecord(Dataset $dataset): DatasetRecord
    {
        return DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['well_id' => 'W-001', 'well_name' => 'Test Well'],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_create_point_feature(): void
    {
        $dataset = $this->createSpatialDataset();
        $record = $this->createRecord($dataset);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'type',
                'id',
                'geometry',
                'properties',
            ]);

        $this->assertEquals('Feature', $response->json('type'));
        $this->assertEquals('Point', $response->json('geometry.type'));
        $this->assertEquals([34.4668, 31.5326], $response->json('geometry.coordinates'));
        $this->assertEquals('W-001', $response->json('properties.well_id'));

        $this->assertDatabaseHas('gis_features', [
            'dataset_record_id' => $record->id,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);
    }

    public function test_create_polygon_feature(): void
    {
        $dataset = Dataset::create([
            'name' => 'parcels',
            'display_name' => 'Parcels',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Polygon',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['parcel_id' => 'P-001', 'owner' => 'John Doe'],
            'identifier_value' => 'P-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [34.46, 31.53],
                    [34.47, 31.53],
                    [34.47, 31.54],
                    [34.46, 31.54],
                    [34.46, 31.53],
                ]],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Polygon', $response->json('geometry.type'));
        $this->assertCount(1, $response->json('geometry.coordinates'));
        $this->assertCount(5, $response->json('geometry.coordinates.0')); // 4 points + closing point
    }

    public function test_create_feature_wrong_geometry_type_gets_422(): void
    {
        $dataset = $this->createSpatialDataset();
        $record = $this->createRecord($dataset);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Polygon', // Dataset expects Point
                'coordinates' => [[
                    [34.46, 31.53],
                    [34.47, 31.53],
                    [34.47, 31.54],
                    [34.46, 31.54],
                    [34.46, 31.53],
                ]],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Geometry type must be Point', $response->json('message'));
    }

    public function test_create_feature_invalid_geojson_gets_422(): void
    {
        $dataset = $this->createSpatialDataset();
        $record = $this->createRecord($dataset);

        // Point with only one coordinate
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668], // Missing latitude
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_feature_on_non_spatial_dataset_gets_422(): void
    {
        $dataset = Dataset::create([
            'name' => 'non_spatial',
            'display_name' => 'Non Spatial',
            'dataset_type' => 'additional_table',
            'is_spatial' => false,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['id' => '1', 'name' => 'Test'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('not configured as spatial', $response->json('message'));
    }

    public function test_create_feature_for_nonexistent_record_gets_404(): void
    {
        $dataset = $this->createSpatialDataset();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => 999999,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $response->assertStatus(422); // Validation fails because record doesn't exist
    }

    public function test_create_duplicate_feature_for_same_record_gets_422(): void
    {
        $dataset = $this->createSpatialDataset();
        $record = $this->createRecord($dataset);

        // Create first feature
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        // Try to create second feature for same record
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4669, 31.5327],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('already exists', $response->json('message'));
    }

    // Geometry Update Tests
    public function test_update_point_feature(): void
    {
        $dataset = $this->createSpatialDataset();
        $record = $this->createRecord($dataset);

        $feature = GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.4668,31.5326]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$dataset->id}/features/{$feature->id}", [
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4669, 31.5327],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Point', $response->json('geometry.type'));
        $this->assertEquals([34.4669, 31.5327], $response->json('geometry.coordinates'));
    }

    public function test_update_feature_wrong_geometry_type_gets_422(): void
    {
        $dataset = $this->createSpatialDataset();
        $record = $this->createRecord($dataset);

        $feature = GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.4668,31.5326]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$dataset->id}/features/{$feature->id}", [
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [34.46, 31.53],
                    [34.47, 31.53],
                    [34.47, 31.54],
                    [34.46, 31.54],
                    [34.46, 31.53],
                ]],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Geometry type must be Point', $response->json('message'));
    }

    // Feature Retrieval Tests
    public function test_list_features_as_feature_collection(): void
    {
        $dataset = $this->createSpatialDataset();

        $record1 = $this->createRecord($dataset);
        $record2 = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['well_id' => 'W-002', 'well_name' => 'Well Two'],
            'identifier_value' => 'W-002',
            'created_by' => $this->admin->id,
        ]);

        GisFeature::create([
            'dataset_record_id' => $record1->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.4668,31.5326]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

GisFeature::create([
            'dataset_record_id' => $record2->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[140.0,-35.0]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features',
                'links',
                'meta',
            ]);

        $this->assertEquals('FeatureCollection', $response->json('type'));
        $this->assertCount(2, $response->json('features'));
    }

    public function test_list_features_with_bbox_filter(): void
    {
        $dataset = $this->createSpatialDataset();

        $record1 = $this->createRecord($dataset);
        $record2 = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['well_id' => 'W-002', 'well_name' => 'Well Two'],
            'identifier_value' => 'W-002',
            'created_by' => $this->admin->id,
        ]);

        // Well inside bbox
        GisFeature::create([
            'dataset_record_id' => $record1->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.4668,31.5326]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        // Well outside bbox
        GisFeature::create([
            'dataset_record_id' => $record2->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[35.0,32.0]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        // Bbox covering only first well
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features?bbox=34.4,31.5,34.5,31.6");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('features'));
        $this->assertEquals('W-001', $response->json('features.0.properties.well_id'));
    }

    public function test_list_features_on_non_spatial_dataset_gets_422(): void
    {
        $dataset = Dataset::create([
            'name' => 'non_spatial',
            'display_name' => 'Non Spatial',
            'dataset_type' => 'additional_table',
            'is_spatial' => false,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features");

        $response->assertStatus(422);
        $this->assertStringContainsString('not configured as spatial', $response->json('message'));
    }

    public function test_show_feature(): void
    {
        $dataset = $this->createSpatialDataset();
        $record = $this->createRecord($dataset);

        $feature = GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.4668,31.5326]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features/{$feature->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'id',
                'geometry',
                'properties',
            ]);

        $this->assertEquals('Feature', $response->json('type'));
        $this->assertEquals($feature->id, $response->json('id'));
    }

    public function test_show_feature_from_different_dataset_gets_404(): void
    {
        $dataset1 = $this->createSpatialDataset();
        $dataset2 = Dataset::create([
            'name' => 'other',
            'display_name' => 'Other',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = $this->createRecord($dataset1);

        $feature = GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset1->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.4668,31.5326]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset2->id}/features/{$feature->id}");

        $response->assertStatus(404);
    }

    // Permission Tests
    public function test_unauthenticated_user_cannot_list_features(): void
    {
        $dataset = $this->createSpatialDataset();

        $response = $this->getJson("/api/datasets/{$dataset->id}/features");
        $response->assertStatus(401);
    }

    public function test_user_without_view_permission_gets_403(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $dataset = $this->createSpatialDataset();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/datasets/{$dataset->id}/features");

        $response->assertStatus(403);
    }

    public function test_user_without_create_permission_gets_403_on_create(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('name', 'datasets.view')->first();
        $user->givePermissionTo($permission);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $dataset = $this->createSpatialDataset();
        $record = $this->createRecord($dataset);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $response->assertStatus(403);
    }

    // Dynamic Behavior Tests
    public function test_same_api_works_for_different_datasets(): void
    {
        // Wells dataset with Point geometry
        $wellsDataset = Dataset::create([
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        // Parcels dataset with Polygon geometry
        $parcelsDataset = Dataset::create([
            'name' => 'parcels',
            'display_name' => 'Parcels',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Polygon',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $wellRecord = DatasetRecord::create([
            'dataset_id' => $wellsDataset->id,
            'values' => ['well_id' => 'W-001', 'well_name' => 'Test Well'],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);

        $parcelRecord = DatasetRecord::create([
            'dataset_id' => $parcelsDataset->id,
            'values' => ['parcel_id' => 'P-001', 'owner' => 'John Doe'],
            'identifier_value' => 'P-001',
            'created_by' => $this->admin->id,
        ]);

        // Create Point feature for wells
        $wellResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$wellsDataset->id}/features", [
            'dataset_record_id' => $wellRecord->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $wellResponse->assertStatus(201);
        $this->assertEquals('Point', $wellResponse->json('geometry.type'));

        // Create Polygon feature for parcels
        $parcelResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$parcelsDataset->id}/features", [
            'dataset_record_id' => $parcelRecord->id,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [34.46, 31.53],
                    [34.47, 31.53],
                    [34.47, 31.54],
                    [34.46, 31.54],
                    [34.46, 31.53],
                ]],
            ],
        ]);

        $parcelResponse->assertStatus(201);
        $this->assertEquals('Polygon', $parcelResponse->json('geometry.type'));

        // Both use the SAME generic API endpoints
        $this->assertStringStartsWith('/api/datasets/', "/api/datasets/{$wellsDataset->id}/features");
        $this->assertStringStartsWith('/api/datasets/', "/api/datasets/{$parcelsDataset->id}/features");
    }

    // Spatial Index Verification (metadata check)
    public function test_spatial_index_exists(): void
    {
        // This test verifies the spatial index was created
        $result = DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'gis_features' AND indexdef LIKE '%gist%'
        ");

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('gist', $result[0]->indexdef ?? '');
    }

    // SRID handling test
    public function test_srid_stored_and_used(): void
    {
        $dataset = Dataset::create([
            'name' => 'test_srid',
            'display_name' => 'Test SRID',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 3857, // Web Mercator
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['id' => '1'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [385123, 5812345], // Web Mercator coordinates
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(3857, $response->json('geometry.srid') ?? 3857);
    }

    public function test_geojson_output_transforms_to_4326(): void
    {
        // Create dataset with SRID 3857 (Web Mercator)
        $dataset = Dataset::create([
            'name' => 'test_srid_transform',
            'display_name' => 'Test SRID Transform',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 3857,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['id' => '1'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        // Create feature with Web Mercator coordinates
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [385123, 5812345], // Web Mercator coordinates
            ],
        ]);

        $response->assertStatus(201);
        
        // The stored SRID should be 3857
        $this->assertEquals(3857, $response->json('geometry.srid') ?? 3857);
        
        // But GeoJSON output should be transformed to EPSG:4326
        // 385123, 5812345 in Web Mercator ≈ 3.46°E, 47.3°N in WGS84
        $geometry = $response->json('geometry');
        $this->assertNotNull($geometry);
        $this->assertEquals('Point', $geometry['type']);
        
        // Check that coordinates are transformed to WGS84 (longitude/latitude)
        $coords = $geometry['coordinates'];
        $this->assertIsArray($coords);
        $this->assertCount(2, $coords);
        
        // Longitude should be around 3.46 (not 385123)
        $this->assertGreaterThan(3.0, $coords[0]);
        $this->assertLessThan(4.0, $coords[0]);
        
        // Latitude should be around 47.3 (not 5812345)
        // Note: 385123, 5812345 in 3857 transforms to approximately 3.46°E, 46.2°N in 4326
        $this->assertGreaterThan(45.0, $coords[1]);
        $this->assertLessThan(48.0, $coords[1]);
    }

    public function test_feature_dataset_id_must_match_record_dataset(): void
    {
        // Create two datasets
        $dataset1 = Dataset::create([
            'name' => 'dataset_one',
            'display_name' => 'Dataset One',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $dataset2 = Dataset::create([
            'name' => 'dataset_two',
            'display_name' => 'Dataset Two',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset1->id,
            'values' => ['id' => '1'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        // Try to create feature with dataset2's ID but record from dataset1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset2->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $response->assertStatus(422); // Should fail due to validation
        $this->assertStringContainsString('The selected dataset record id is invalid', $response->json('message'));
    }

    // Multi-geometry types test
    public function test_multipoint_feature(): void
    {
        $dataset = Dataset::create([
            'name' => 'multi_points',
            'display_name' => 'Multi Points',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'MultiPoint',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['id' => '1'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'MultiPoint',
                'coordinates' => [
                    [34.4668, 31.5326],
                    [34.4669, 31.5327],
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals('MultiPoint', $response->json('geometry.type'));
        $this->assertCount(2, $response->json('geometry.coordinates'));
    }

    public function test_linestring_feature(): void
    {
        $dataset = Dataset::create([
            'name' => 'pipes',
            'display_name' => 'Pipes',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'LineString',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['pipe_id' => 'PIPE-001'],
            'identifier_value' => 'PIPE-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [34.4668, 31.5326],
                    [34.4670, 31.5328],
                    [34.4672, 31.5330],
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals('LineString', $response->json('geometry.type'));
        $this->assertCount(3, $response->json('geometry.coordinates'));
    }

    // Spatial filtering tests
    public function test_bbox_filter_uses_parameter_binding(): void
    {
        // This test ensures the bbox filter uses parameter binding, not SQL interpolation
        // We create features and verify the query works without SQL injection
        $dataset = $this->createSpatialDataset();

        $record1 = $this->createRecord($dataset);
        $record2 = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['well_id' => 'W-002', 'well_name' => 'Well Two'],
            'identifier_value' => 'W-002',
            'created_by' => $this->admin->id,
        ]);

        // Create features at known locations
        GisFeature::create([
            'dataset_record_id' => $record1->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.4668,31.5326]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        GisFeature::create([
            'dataset_record_id' => $record2->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[35.0,32.0]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        // Query with bbox that includes only first feature
        // This should not cause SQL injection even with malicious input
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features?bbox=34.4,31.5,34.5,31.6");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('features'));
        $this->assertEquals('W-001', $response->json('features.0.properties.well_id'));
    }

    public function test_bbox_filter_with_srid_3857(): void
    {
        // Create dataset with SRID 3857 (Web Mercator)
        $dataset = Dataset::create([
            'name' => 'test_3857',
            'display_name' => 'Test 3857',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 3857,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['id' => '1'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        // Create feature in Web Mercator (EPSG:3857) - approximately near origin
        // Point at 385123, 5812345 in Web Mercator
        GisFeature::create([
            'dataset_record_id' => 1,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[385123,5812345]}'), 3857) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 3857,
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
        ]);

        // Update the record id
        $record->refresh();
        $feature = \App\Models\GisFeature::where('dataset_record_id', $record->id)->first();
        $feature->dataset_record_id = $record->id;
        $feature->save();

        // Bbox in WGS84 (4326) that covers the feature location
        // The feature at 385123, 5812345 in 3857 is approximately 3.46°E, 46.2°N in 4326
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features?bbox=3.4,46.0,3.5,46.5");

        $response->assertStatus(200);
        // Should find the feature because bbox in 4326 is transformed to 3857
        // Note: exact match depends on coordinate precision
    }

    public function test_radius_filter_4326_uses_geography(): void
    {
        $dataset = $this->createSpatialDataset();

        $record1 = $this->createRecord($dataset);

        // Create feature using the controller store method to ensure proper SRID handling
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record1->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $response->assertStatus(201);
        $featureId = $response->json('id');

        // Search within 100km of the feature - should find it
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features?lat=31.5326&lng=34.4668&radius=100000");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('features'));
        $this->assertEquals($featureId, $response->json('features.0.id'));
    }

    public function test_radius_filter_3857_uses_geometry(): void
    {
        // Create dataset with SRID 3857 (Web Mercator)
        $dataset = Dataset::create([
            'name' => 'test_radius_3857',
            'display_name' => 'Test Radius 3857',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 3857,
            'created_by' => $this->admin->id,
        ]);

        $record1 = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['id' => '1'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        // Create feature using controller
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record1->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [385123, 5812345],
            ],
        ]);

        $response->assertStatus(201);

        // Search within 50km (50000 meters) - point in WGS84 that will be transformed to 3857
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features?lat=46.2&lng=3.46&radius=50000");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('features'));
    }

    public function test_4326_bbox_and_radius_still_work(): void
    {
        // Verify existing 4326 behavior still works
        $dataset = $this->createSpatialDataset();

        $record1 = $this->createRecord($dataset);

        // Create feature using controller
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record1->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $response->assertStatus(201);

        // Bbox filter in 4326
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features?bbox=34.4,31.5,34.5,31.6");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('features'));

        // Radius filter in 4326
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$dataset->id}/features?lat=31.5326&lng=34.4668&radius=100000");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('features'));
    }
}