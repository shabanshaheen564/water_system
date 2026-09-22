<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Shapefile\Shapefile;
use Shapefile\ShapefileWriter;
use Shapefile\Geometry\Point;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GisImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        foreach (['datasets.view', 'datasets.create'] as $permission) {
            $this->admin->givePermissionTo(Permission::where('name', $permission)->first());
        }
    }

    public function test_shapefile_preview_detects_geometry_crs_and_fields(): void
    {
        $dir = $this->makePointShapefile();

        try {
            $response = $this->actingAs($this->admin)->post('/datasets/import/preview', [
                'files' => $this->uploadedFiles($dir),
            ]);

            $response->assertStatus(200)->assertViewIs('datasets.import-preview');
            $info = $response->viewData('info');

            $this->assertSame('Point', $info['geometry_type']);
            $this->assertSame(28191, $info['srid']);
            $this->assertTrue($info['prj_present']);
            $this->assertSame(1, $info['record_count']);
            $this->assertArrayHasKey('NAME', $info['fields']);
        } finally {
            $this->removeDirectory($dir);
        }
    }

    public function test_shapefile_confirm_creates_dataset_fields_records_features_and_import_log(): void
    {
        $dir = $this->makePointShapefile();

        try {
            $preview = $this->actingAs($this->admin)->post('/datasets/import/preview', [
                'files' => $this->uploadedFiles($dir),
            ]);

            $preview->assertStatus(200);
            $token = $preview->viewData('token');

            $response = $this->actingAs($this->admin)->post('/datasets/import/confirm', [
                'token' => $token,
                'name' => 'water_points_import',
                'display_name' => 'Water Points Import',
                'management_mode' => 'official',
                'srid' => 28191,
                'source_name' => 'water_points.shp',
            ]);

            $response->assertRedirect();

            $dataset = Dataset::where('name', 'water_points_import')->firstOrFail();
            $this->assertSame(28191, (int) $dataset->srid);
            $this->assertSame('Point', $dataset->geometry_type);
            $this->assertTrue($dataset->is_spatial);

            $this->assertDatabaseHas('dataset_fields', [
                'dataset_id' => $dataset->id,
                'name' => 'NAME',
            ]);

            $record = DatasetRecord::where('dataset_id', $dataset->id)->first();
            $this->assertNotNull($record);
            $this->assertSame('Well A', $record->values['NAME']);

            $feature = DB::table('gis_features')->where('dataset_id', $dataset->id)->first();
            $this->assertNotNull($feature);
            $this->assertSame(28191, (int) $feature->srid);

            $import = DB::table('dataset_imports')->where('dataset_id', $dataset->id)->first();
            $this->assertNotNull($import);
            $this->assertSame('completed', $import->status);
            $this->assertSame(1, (int) $import->total_rows);
            $this->assertSame(1, (int) $import->successful_rows);
            $this->assertSame(0, (int) $import->failed_rows);
        } finally {
            $this->removeDirectory($dir);
        }
    }

    public function test_geojson_export_transforms_source_geometry_to_wgs84(): void
    {
        $dataset = $this->makeSpatialDataset('geojson_export', 28191);
        $record = $this->makeRecord($dataset, 'Well A');

        DB::insert(
            'INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
             VALUES (?, ?, ST_GeomFromText(?, ?), ?, ?, NOW(), NOW())',
            [$record->id, $dataset->id, 'POINT(88308.60431627806 91357.86481704746)', 28191, 'Point', 28191]
        );

        $response = $this->actingAs($this->admin)->get(route('datasets.export.geojson', $dataset));

        $response->assertOk();
        $json = $response->json();

        $this->assertSame('FeatureCollection', $json['type']);
        $this->assertSame('EPSG:4326', $json['crs']['properties']['name']);
        $this->assertCount(1, $json['features']);
        $this->assertSame('Well A', $json['features'][0]['properties']['NAME']);
        $this->assertSame('Point', $json['features'][0]['geometry']['type']);
        $this->assertNotEquals(88308.60431627806, $json['features'][0]['geometry']['coordinates'][0]);
        $this->assertNotEquals(91357.86481704746, $json['features'][0]['geometry']['coordinates'][1]);
    }

    public function test_csv_export_contains_attributes_and_wgs84_coordinates(): void
    {
        $dataset = $this->makeSpatialDataset('csv_export', 28191);
        $record = $this->makeRecord($dataset, 'Well A');

        DB::insert(
            'INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
             VALUES (?, ?, ST_GeomFromText(?, ?), ?, ?, NOW(), NOW())',
            [$record->id, $dataset->id, 'POINT(88308.60431627806 91357.86481704746)', 28191, 'Point', 28191,]
        );

        $response = $this->actingAs($this->admin)->get(route('datasets.export.csv', $dataset));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('NAME', $content);
        $this->assertStringContainsString('latitude', $content);
        $this->assertStringContainsString('longitude', $content);
        $this->assertStringContainsString('Well A', $content);
        $this->assertStringNotContainsString('88308.60431627806', $content);
        $this->assertStringNotContainsString('91357.86481704746', $content);
    }

    public function test_shapefile_export_contains_shp_shx_dbf_and_prj(): void
    {
        $dataset = $this->makeSpatialDataset('shapefile_export', 28191);
        $record = $this->makeRecord($dataset, 'Well A');

        DB::insert(
            'INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
             VALUES (?, ?, ST_GeomFromText(?, ?), ?, ?, NOW(), NOW())',
            [$record->id, $dataset->id, 'POINT(88308.60431627806 91357.86481704746)', 28191, 'Point', 28191]
        );

        $response = $this->actingAs($this->admin)->get(route('datasets.export.shapefile', $dataset));

        $response->assertOk();
        $path = $response->getFile()->getPathname();

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('shapefile_export.shp'));
        $this->assertNotFalse($zip->locateName('shapefile_export.shx'));
        $this->assertNotFalse($zip->locateName('shapefile_export.dbf'));
        $this->assertNotFalse($zip->locateName('shapefile_export.prj'));
        $zip->close();
    }

    private function makeSpatialDataset(string $name, int $srid): Dataset
    {
        $dataset = Dataset::create([
            'name' => $name,
            'display_name' => $name,
            'dataset_type' => 'official_layer',
            'management_mode' => 'official',
            'source_format' => 'Shapefile',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => $srid,
            'created_by' => $this->admin->id,
        ]);

        DatasetField::create([
            'dataset_id' => $dataset->id,
            'name' => 'NAME',
            'display_name' => 'Name',
            'data_type' => 'string',
            'sort_order' => 0,
        ]);

        return $dataset;
    }

    private function makeRecord(Dataset $dataset, string $name): DatasetRecord
    {
        return DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['NAME' => $name],
            'identifier_value' => $name,
            'created_by' => $this->admin->id,
        ]);
    }

    private function makePointShapefile(): string
    {
        $dir = storage_path('app/testing/gis6_'.uniqid());
        mkdir($dir, 0775, true);

        $base = $dir.'/water_points';
        $writer = new ShapefileWriter($base.'.shp');
        $writer->setShapeType(Shapefile::SHAPE_TYPE_POINT);
        $writer->addCharField('NAME', 50);

        $point = new Point(88308.60431627806, 91357.86481704746);
        $point->setData('NAME', 'Well A');
        $writer->writeRecord($point);
        $writer = null;

        file_put_contents($base.'.prj', $this->palestineGridPrj());

        return $dir;
    }

    private function uploadedFiles(string $dir): array
    {
        return [
            new UploadedFile($dir.'/water_points.shp', 'water_points.shp', 'application/octet-stream', null, true),
            new UploadedFile($dir.'/water_points.shx', 'water_points.shx', 'application/octet-stream', null, true),
            new UploadedFile($dir.'/water_points.dbf', 'water_points.dbf', 'application/octet-stream', null, true),
            new UploadedFile($dir.'/water_points.prj', 'water_points.prj', 'text/plain', null, true),
        ];
    }

    private function palestineGridPrj(): string
    {
        return 'PROJCS["Palestine_1923_Palestine_Grid",GEOGCS["GCS_Palestine_1923",DATUM["D_Palestine_1923",SPHEROID["Clarke_1880_Benoit",6378300.789,293.4663155389802]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]],PROJECTION["Cassini"],PARAMETER["False_Easting",170251.555],PARAMETER["False_Northing",126867.909],PARAMETER["Central_Meridian",35.21208055555556],PARAMETER["Scale_Factor",1.0],PARAMETER["Latitude_Of_Origin",31.73409694444445],UNIT["Meter",1.0]]';
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        rmdir($dir);
    }
}
