<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GisValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(Permission::where('name', 'datasets.view')->first());
    }

    public function test_validation_detects_geometry_and_attribute_quality_errors(): void
    {
        $dataset = $this->makePolygonDataset();

        $r1 = $this->makeRecord($dataset, ['NAME' => 'A'], 'P1');
        $r2 = $this->makeRecord($dataset, ['NAME' => null], 'P2');
        $r3 = $this->makeRecord($dataset, ['NAME' => 'C'], 'P3');
        $r4 = $this->makeRecord($dataset, ['NAME' => 'D'], 'P1');

        $validWkt = 'POLYGON((0 0,0 10,10 10,10 0,0 0))';
        $invalidWkt = 'POLYGON((0 0,10 10,10 0,0 10,0 0))';

        $this->insertFeature($dataset, $r1, $validWkt, 4326);
        $this->insertFeature($dataset, $r2, $invalidWkt, 4326);
        $this->insertFeature($dataset, $r4, $validWkt, 4326);

        $response = $this->actingAs($this->admin)->getJson(route('datasets.validation.api', $dataset));

        $response->assertOk()
            ->assertJsonPath('summary.total_records', 4)
            ->assertJsonPath('summary.valid_geometry', 2)
            ->assertJsonPath('summary.invalid_geometry', 1)
            ->assertJsonPath('summary.missing_geometry', 1)
            ->assertJsonPath('summary.empty_geometry', 0)
            ->assertJsonPath('summary.srid_errors', 3)
            ->assertJsonPath('summary.geometry_type_errors', 0)
            ->assertJsonPath('summary.attribute_errors', 1)
            ->assertJsonPath('summary.duplicate_identifiers', 1)
            ->assertJsonPath('summary.duplicate_geometries', 1);

        $types = collect($response->json('details'))->pluck('type');
        $this->assertTrue($types->contains('invalid_geometry'));
        $this->assertTrue($types->contains('missing_geometry'));
        $this->assertTrue($types->contains('srid_mismatch'));
        $this->assertTrue($types->contains('required_field'));
        $this->assertTrue($types->contains('duplicate_identifier'));
        $this->assertTrue($types->contains('duplicate_geometry'));
    }

    public function test_validation_detects_geometry_type_mismatch_and_empty_geometry(): void
    {
        $dataset = $this->makePolygonDataset('quality_type');

        $record = $this->makeRecord($dataset, ['NAME' => 'A'], 'P1');
        $empty = $this->makeRecord($dataset, ['NAME' => 'B'], 'P2');

        $this->insertFeature($dataset, $record, 'POINT(10 20)', 28191, 'Point');
        $this->insertFeature($dataset, $empty, 'POLYGON EMPTY', 28191);

        $response = $this->actingAs($this->admin)->getJson(route('datasets.validation.api', $dataset));

        $response->assertOk()
            ->assertJsonPath('summary.geometry_type_errors', 1)
            ->assertJsonPath('summary.empty_geometry', 1)
            ->assertJsonPath('summary.srid_errors', 0);
    }

    public function test_validation_checks_attribute_data_types_for_non_spatial_dataset(): void
    {
        $dataset = Dataset::create([
            'name' => 'attribute_quality',
            'display_name' => 'Attribute Quality',
            'dataset_type' => 'official_layer',
            'management_mode' => 'official',
            'is_active' => true,
            'is_spatial' => false,
            'created_by' => $this->admin->id,
        ]);

        DatasetField::create([
            'dataset_id' => $dataset->id,
            'name' => 'DEPTH',
            'display_name' => 'Depth',
            'data_type' => 'integer',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['DEPTH' => 'not-a-number'],
            'identifier_value' => null,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('datasets.validation.api', $dataset));

        $response->assertOk()
            ->assertJsonPath('summary.total_records', 1)
            ->assertJsonPath('summary.attribute_errors', 1)
            ->assertJsonPath('summary.valid_geometry', 0);
    }

    public function test_validation_web_page_renders_quality_report(): void
    {
        $dataset = $this->makePolygonDataset('quality_web');
        $response = $this->actingAs($this->admin)->get(route('datasets.validation', $dataset));

        $response->assertOk()
            ->assertViewIs('datasets.validation')
            ->assertSee('جودة بيانات GIS')
            ->assertSee($dataset->display_name);
    }

    private function makePolygonDataset(string $name = 'quality_polygon'): Dataset
    {
        $dataset = Dataset::create([
            'name' => $name,
            'display_name' => $name,
            'dataset_type' => 'official_layer',
            'management_mode' => 'official',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Polygon',
            'srid' => 28191,
            'created_by' => $this->admin->id,
        ]);

        DatasetField::create([
            'dataset_id' => $dataset->id,
            'name' => 'NAME',
            'display_name' => 'Name',
            'data_type' => 'string',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        DatasetField::create([
            'dataset_id' => $dataset->id,
            'name' => 'ASSET_ID',
            'display_name' => 'Asset ID',
            'data_type' => 'string',
            'is_identifier' => true,
            'sort_order' => 1,
        ]);

        return $dataset;
    }

    private function makeRecord(Dataset $dataset, array $values, string $identifier): DatasetRecord
    {
        return DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => $values,
            'identifier_value' => $identifier,
            'created_by' => $this->admin->id,
        ]);
    }

    private function insertFeature(Dataset $dataset, DatasetRecord $record, string $wkt, int $srid, string $geometryType = 'Polygon'): void
    {
        DB::insert(
            'INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
             VALUES (?, ?, ST_SetSRID(ST_GeomFromText(?), ?), ?, ?, NOW(), NOW())',
            [$record->id, $dataset->id, $wkt, $srid, $geometryType, $srid]
        );
    }
}
