<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GisFoundationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        foreach (['datasets.create', 'datasets.view', 'datasets.update'] as $permissionName) {
            $this->admin->givePermissionTo(Permission::where('name', $permissionName)->first());
        }

        $this->token = $this->admin->createToken('gis-foundation-tests')->plainTextToken;
    }

    public function test_spatial_dataset_requires_a_registered_postgis_srid(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/datasets', [
            'name' => 'invalid_srid_layer',
            'display_name' => 'Invalid SRID Layer',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('srid');
    }

    public function test_point_coordinates_must_be_numeric(): void
    {
        $dataset = $this->createSpatialDataset('point_layer', 'Point');
        $record = $this->createRecord($dataset);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => ['34.5', '31.5'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('geometry.coordinates');
    }

    public function test_polygon_rings_must_be_closed(): void
    {
        $dataset = $this->createSpatialDataset('polygon_layer', 'Polygon');
        $record = $this->createRecord($dataset);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [34.5, 31.5],
                    [34.6, 31.5],
                    [34.6, 31.6],
                    [34.5, 31.6],
                ]],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('geometry.coordinates');
    }

    public function test_database_rejects_geometry_with_wrong_srid(): void
    {
        $dataset = $this->createSpatialDataset('srid_integrity', 'Point');
        $record = $this->createRecord($dataset);

        $this->expectException(QueryException::class);

        GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne(
                "SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 3857) AS geometry",
                [json_encode(['type' => 'Point', 'coordinates' => [34.5, 31.5]])]
            )->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);
    }

    public function test_database_rejects_geometry_type_that_does_not_match_metadata(): void
    {
        $dataset = $this->createSpatialDataset('type_integrity', 'Point');
        $record = $this->createRecord($dataset);

        $this->expectException(QueryException::class);

        GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::selectOne(
                "SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geometry",
                [json_encode(['type' => 'Point', 'coordinates' => [34.5, 31.5]])]
            )->geometry,
            'geometry_type' => 'Polygon',
            'srid' => 4326,
        ]);
    }

    private function createSpatialDataset(string $name, string $geometryType): Dataset
    {
        return Dataset::create([
            'name' => $name,
            'display_name' => $name,
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => $geometryType,
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);
    }

    private function createRecord(Dataset $dataset): DatasetRecord
    {
        return DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['name' => 'Test Feature'],
            'identifier_value' => 'F-' . $dataset->name,
            'created_by' => $this->admin->id,
        ]);
    }
}
