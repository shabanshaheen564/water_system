<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetImport;
use App\Models\DatasetRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatasetImportHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $token;
    protected Dataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        foreach (['datasets.create', 'datasets.view', 'datasets.update'] as $permissionName) {
            $this->admin->givePermissionTo(Permission::where('name', $permissionName)->first());
        }
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->dataset = Dataset::create([
            'name' => 'imports-test',
            'display_name' => 'Imports Test',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
        ]);

        DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'well_name',
            'display_name' => 'Well Name',
            'data_type' => 'string',
        ]);

        DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'depth',
            'display_name' => 'Depth',
            'data_type' => 'integer',
        ]);
    }

    private function import(string $content, array $mapping, string $filename = 'test.csv')
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => UploadedFile::fake()->createWithContent($filename, $content),
            'column_mapping' => $mapping,
        ]);
    }

    public function test_show_import_cannot_cross_dataset_boundary(): void
    {
        $otherDataset = Dataset::create([
            'name' => 'other-dataset',
            'display_name' => 'Other Dataset',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $import = DatasetImport::create([
            'dataset_id' => $otherDataset->id,
            'original_filename' => 'other.csv',
            'source_format' => 'csv',
            'imported_by' => $this->admin->id,
            'started_at' => now(),
            'status' => 'completed',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/datasets/{$this->dataset->id}/imports/{$import->id}")
            ->assertStatus(404);
    }

    public function test_semicolon_csv_and_utf8_bom_are_supported(): void
    {
        $content = "\xEF\xBB\xBFwell_id;well_name;depth\nW-001;آبار المدينة;100\n";

        $response = $this->import($content, [
            'well_id' => 'well_id',
            'well_name' => 'well_name',
            'depth' => 'depth',
        ]);

        $response->assertStatus(201);
        $this->assertSame('completed', $response->json('status'));
        $this->assertSame(1, $response->json('successful_rows'));
        $this->assertDatabaseHas('dataset_records', [
            'dataset_id' => $this->dataset->id,
            'identifier_value' => 'W-001',
        ]);
    }

    public function test_invalid_integer_is_rejected(): void
    {
        $response = $this->import(
            "well_id,well_name,depth\nW-001,Well One,not-a-number\n",
            ['well_id' => 'well_id', 'well_name' => 'well_name', 'depth' => 'depth']
        );

        $response->assertStatus(201);
        $this->assertSame('failed', $response->json('status'));
        $this->assertSame(1, $response->json('failed_rows'));
        $this->assertStringContainsString('Invalid integer', $response->json('error_summary.0.error'));
    }

    public function test_required_field_is_enforced(): void
    {
        DatasetField::where('dataset_id', $this->dataset->id)
            ->where('name', 'well_name')
            ->update(['is_required' => true]);

        $response = $this->import(
            "well_id,well_name,depth\nW-001,,100\n",
            ['well_id' => 'well_id', 'well_name' => 'well_name', 'depth' => 'depth']
        );

        $response->assertStatus(201);
        $this->assertSame('failed', $response->json('status'));
        $this->assertSame(1, $response->json('failed_rows'));
        $this->assertStringContainsString('Required field', $response->json('error_summary.0.error'));
    }

    public function test_duplicate_unique_value_inside_same_file_is_rejected(): void
    {
        $response = $this->import(
            "well_id,well_name,depth\nW-001,Well One,100\nW-001,Well Duplicate,200\n",
            ['well_id' => 'well_id', 'well_name' => 'well_name', 'depth' => 'depth']
        );

        $response->assertStatus(201);
        $this->assertSame('partial', $response->json('status'));
        $this->assertSame(1, $response->json('successful_rows'));
        $this->assertSame(1, $response->json('failed_rows'));
        $this->assertStringContainsString('duplicated within this import', $response->json('error_summary.0.error'));
        $this->assertSame(1, DatasetRecord::where('dataset_id', $this->dataset->id)->count());
    }

    public function test_error_summary_does_not_store_full_row_data(): void
    {
        $response = $this->import(
            "well_id,well_name,depth\nW-001,Well One,invalid\n",
            ['well_id' => 'well_id', 'well_name' => 'well_name', 'depth' => 'depth']
        );

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('data', $response->json('error_summary.0'));
    }

    public function test_inconsistent_csv_row_is_reported_and_does_not_hide_valid_rows(): void
    {
        $response = $this->import(
            "well_id,well_name,depth\nW-001,Well One,100\nW-002,Well Two\n",
            ['well_id' => 'well_id', 'well_name' => 'well_name', 'depth' => 'depth']
        );

        $response->assertStatus(201);
        $this->assertSame('partial', $response->json('status'));
        $this->assertSame(2, $response->json('total_rows'));
        $this->assertSame(1, $response->json('successful_rows'));
        $this->assertSame(1, $response->json('failed_rows'));
        $this->assertStringContainsString('Column count', $response->json('error_summary.0.error'));
    }
}
