<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GisWebTest extends TestCase
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
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('datasets.view');
        $this->userToken = $this->user->createToken('test')->plainTextToken;

        $this->viewer = User::factory()->create();
        $this->viewerToken = $this->viewer->createToken('test')->plainTextToken;
    }

    public function test_gis_page_accessible_to_authorized_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200)
            ->assertViewIs('gis.index')
            ->assertViewHas('spatialDatasets');
    }

    public function test_gis_page_denied_to_unauthenticated_user(): void
    {
        $response = $this->get('/gis');
        $response->assertStatus(302);
    }

    public function test_gis_page_denied_to_user_without_permission(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get('/gis');
        $response->assertStatus(403);
    }

    public function test_gis_page_shows_only_active_spatial_datasets(): void
    {
        $activeSpatial = Dataset::create([
            'name' => 'active_spatial',
            'display_name' => 'Active Spatial',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $inactiveSpatial = Dataset::create([
            'name' => 'inactive_spatial',
            'display_name' => 'Inactive Spatial',
            'dataset_type' => 'official_layer',
            'is_active' => false,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $activeNonSpatial = Dataset::create([
            'name' => 'active_nonspatial',
            'display_name' => 'Active Non-Spatial',
            'dataset_type' => 'additional_table',
            'is_active' => true,
            'is_spatial' => false,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets', function ($datasets) use ($activeSpatial, $inactiveSpatial, $activeNonSpatial) {
            return $datasets->contains('id', $activeSpatial->id)
                && !$datasets->contains('id', $inactiveSpatial->id)
                && !$datasets->contains('id', $activeNonSpatial->id);
        });
    }

    public function test_datasets_page_accessible_to_authorized_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/datasets');

        $response->assertStatus(200)
            ->assertViewIs('datasets.index')
            ->assertViewHas('datasets');
    }

    public function test_datasets_page_denied_to_unauthenticated_user(): void
    {
        $response = $this->get('/datasets');
        $response->assertStatus(302);
    }

    public function test_datasets_page_denied_to_user_without_permission(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get('/datasets');
        $response->assertStatus(403);
    }

    public function test_datasets_page_shows_all_datasets(): void
    {
        Dataset::create([
            'name' => 'dataset1',
            'display_name' => 'Dataset 1',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        Dataset::create([
            'name' => 'dataset2',
            'display_name' => 'Dataset 2',
            'dataset_type' => 'additional_table',
            'is_spatial' => false,
            'is_active' => false,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/datasets');

        $response->assertStatus(200);
        $response->assertViewHas('datasets', function ($datasets) {
            return $datasets->count() >= 2;
        });
    }

    public function test_gis_page_loads_layer_data_from_api(): void
    {
        $dataset = Dataset::create([
            'name' => 'test_layer',
            'display_name' => 'Test Layer',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'is_active' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = \App\Models\DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['name' => 'Test Feature'],
            'identifier_value' => 'F-001',
            'created_by' => $this->admin->id,
        ]);

        \App\Models\GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => \Illuminate\Support\Facades\DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.5,31.5]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets', function ($datasets) use ($dataset) {
            return $datasets->contains('id', $dataset->id);
        });
    }

    public function test_gis_api_still_enforces_authorization(): void
    {
        $dataset = Dataset::create([
            'name' => 'test',
            'display_name' => 'Test',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'is_active' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/datasets/{$dataset->id}/features");
        $response->assertStatus(401);

        $response = $this->actingAs($this->viewer)
            ->getJson("/api/datasets/{$dataset->id}/features");
        $response->assertStatus(403);
    }

    public function test_new_dataset_appears_without_code_changes(): void
    {
        $newDataset = Dataset::create([
            'name' => 'future_layer',
            'display_name' => 'Future Layer',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Polygon',
            'srid' => 3857,
            'created_by' => $this->admin->id,
        ]);

        \App\Models\DatasetField::create([
            'dataset_id' => $newDataset->id,
            'name' => 'name',
            'display_name' => 'Name',
            'data_type' => 'string',
            'is_required' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets', function ($datasets) use ($newDataset) {
            return $datasets->contains('id', $newDataset->id);
        });

        $response = $this->actingAs($this->admin)
            ->get('/datasets');
        $response->assertStatus(200);
    }

    public function test_inactive_datasets_not_shown_as_gis_layers(): void
    {
        $inactiveDataset = Dataset::create([
            'name' => 'inactive_layer',
            'display_name' => 'Inactive Layer',
            'dataset_type' => 'official_layer',
            'is_active' => false,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets', function ($datasets) use ($inactiveDataset) {
            return !$datasets->contains('id', $inactiveDataset->id);
        });
    }

    public function test_non_spatial_datasets_not_shown_as_gis_layers(): void
    {
        $nonSpatialDataset = Dataset::create([
            'name' => 'nonspatial_table',
            'display_name' => 'Non-spatial Table',
            'dataset_type' => 'additional_table',
            'is_active' => true,
            'is_spatial' => false,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets', function ($datasets) use ($nonSpatialDataset) {
            return !$datasets->contains('id', $nonSpatialDataset->id);
        });
    }

    // ============================================================
    // Web Authentication Tests (STEP 15)
    // ============================================================

    public function test_successful_web_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $user->givePermissionTo('datasets.view');

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('gis.index'));
        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    public function test_web_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $user->givePermissionTo('datasets.view');

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);
        $user->givePermissionTo('datasets.view');

        $response = $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_web_logout_invalidates_session(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $user->givePermissionTo('datasets.view');

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/gis');

        $response->assertRedirect(route('login'));
    }

    // ============================================================
    // STEP 16: Dynamic GIS Layer Management Tests
    // ============================================================

    public function test_spatial_datasets_include_feature_count(): void
    {
        $dataset = Dataset::create([
            'name' => 'test_count',
            'display_name' => 'Test Count',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = \App\Models\DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['name' => 'Feature 1'],
            'identifier_value' => 'F-001',
            'created_by' => $this->admin->id,
        ]);

        \App\Models\GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => \Illuminate\Support\Facades\DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.5,31.5]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets', function ($datasets) use ($dataset) {
            $found = $datasets->firstWhere('id', $dataset->id);
            return $found && $found->features_count === 1;
        });
    }

    public function test_geojson_loading_handles_empty_dataset(): void
    {
        $dataset = Dataset::create([
            'name' => 'empty_layer',
            'display_name' => 'Empty Layer',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/datasets/{$dataset->id}/features");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'type',
            'features',
            'links',
            'meta',
        ]);
        $this->assertEquals('FeatureCollection', $response->json('type'));
        $this->assertEquals(0, $response->json('meta.total'));
        $this->assertEmpty($response->json('features'));
    }

    public function test_geojson_loading_handles_api_error(): void
    {
        $dataset = Dataset::create([
            'name' => 'error_layer',
            'display_name' => 'Error Layer',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        // Request non-existent dataset
        $response = $this->actingAs($this->admin)
            ->getJson("/api/datasets/999999/features");

        $response->assertStatus(404);
    }

    public function test_feature_inspection_displays_attributes_dynamically(): void
    {
        $dataset = Dataset::create([
            'name' => 'inspect_layer',
            'display_name' => 'Inspect Layer',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = \App\Models\DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => [
                'well_name' => 'Test Well',
                'depth' => 150,
                'is_active' => true,
                'status' => 'active',
                'notes' => 'Test well for inspection',
            ],
            'identifier_value' => 'INSP-001',
            'created_by' => $this->admin->id,
        ]);

        \App\Models\GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => \Illuminate\Support\Facades\DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.5,31.5]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/datasets/{$dataset->id}/features");

        $response->assertStatus(200);
        $features = $response->json('features');
        $this->assertCount(1, $features);

        $feature = $features[0];
        $this->assertEquals('Feature', $feature['type']);
        $this->assertArrayHasKey('geometry', $feature);
        $this->assertArrayHasKey('properties', $feature);

        $properties = $feature['properties'];
        $this->assertEquals('Test Well', $properties['well_name']);
        $this->assertEquals(150, $properties['depth']);
        $this->assertTrue($properties['is_active']);
        $this->assertEquals('active', $properties['status']);
        $this->assertEquals('Test well for inspection', $properties['notes']);
    }

    public function test_multiple_spatial_datasets_can_be_displayed_simultaneously(): void
    {
        $dataset1 = Dataset::create([
            'name' => 'layer_one',
            'display_name' => 'Layer One',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $dataset2 = Dataset::create([
            'name' => 'layer_two',
            'display_name' => 'Layer Two',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Polygon',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record1 = \App\Models\DatasetRecord::create([
            'dataset_id' => $dataset1->id,
            'values' => ['name' => 'Feature 1'],
            'identifier_value' => 'F-001',
            'created_by' => $this->admin->id,
        ]);

        $record2 = \App\Models\DatasetRecord::create([
            'dataset_id' => $dataset2->id,
            'values' => ['name' => 'Feature 2'],
            'identifier_value' => 'F-002',
            'created_by' => $this->admin->id,
        ]);

        \App\Models\GisFeature::create([
            'dataset_record_id' => $record1->id,
            'dataset_id' => $dataset1->id,
            'geometry' => \Illuminate\Support\Facades\DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.5,31.5]}'), 4326) as geometry")->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

\App\Models\GisFeature::create([
            'dataset_record_id' => $record2->id,
            'dataset_id' => $dataset2->id,
            'geometry' => \Illuminate\Support\Facades\DB::selectOne("SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) as geometry", [json_encode([
                'type' => 'Polygon',
                'coordinates' => [[[34.5, 31.5], [34.6, 31.5], [34.6, 31.6], [34.5, 31.6], [34.5, 31.5]]]
            ])])->geometry,
            'geometry_type' => 'Polygon',
            'srid' => 4326,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets', function ($datasets) use ($dataset1, $dataset2) {
            return $datasets->contains('id', $dataset1->id)
                && $datasets->contains('id', $dataset2->id);
        });
    }

    public function test_geometry_types_supported_dynamically(): void
    {
        $geometryTypes = ['Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'];

        foreach ($geometryTypes as $type) {
            $dataset = Dataset::create([
                'name' => 'layer_' . strtolower($type),
                'display_name' => 'Layer ' . $type,
                'dataset_type' => 'official_layer',
                'is_active' => true,
                'is_spatial' => true,
                'geometry_type' => $type,
                'srid' => 4326,
                'created_by' => $this->admin->id,
            ]);

            $record = \App\Models\DatasetRecord::create([
                'dataset_id' => $dataset->id,
                'values' => ['name' => 'Feature'],
                'identifier_value' => 'F-' . $type,
                'created_by' => $this->admin->id,
            ]);

            // Create appropriate geometry based on type
            $coordinates = $this->getCoordinatesForGeometryType($type);
            $geojson = json_encode(['type' => $type, 'coordinates' => $coordinates]);

            \App\Models\GisFeature::create([
                'dataset_record_id' => $record->id,
                'dataset_id' => $dataset->id,
                'geometry' => \Illuminate\Support\Facades\DB::selectOne(
                    "SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) as geometry",
                    [$geojson]
                )->geometry,
                'geometry_type' => $type,
                'srid' => 4326,
            ]);
        }

        // All datasets should be loaded and accessible
        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('spatialDatasets', function ($datasets) use ($geometryTypes) {
            foreach ($geometryTypes as $type) {
                $dataset = $datasets->firstWhere('name', 'layer_' . strtolower($type));
                if (!$dataset) {
                    return false;
                }
            }
            return true;
        });
    }

    private function getCoordinatesForGeometryType(string $type): array
    {
        return match ($type) {
            'Point' => [34.5, 31.5],
            'MultiPoint' => [[34.5, 31.5], [34.6, 31.6]],
            'LineString' => [[34.5, 31.5], [34.6, 31.6], [34.7, 31.7]],
            'MultiLineString' => [[[34.5, 31.5], [34.6, 31.6]], [[34.7, 31.7], [34.8, 31.8]]],
            'Polygon' => [[[34.5, 31.5], [34.6, 31.5], [34.6, 31.6], [34.5, 31.6], [34.5, 31.5]]],
            'MultiPolygon' => [[[[34.5, 31.5], [34.6, 31.5], [34.6, 31.6], [34.5, 31.6], [34.5, 31.5]]]],
            default => [34.5, 31.5],
        };
    }
}