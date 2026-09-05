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

class DatasetImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected Dataset $dataset;
    protected DatasetField $identifierField;
    protected DatasetField $stringField;
    protected DatasetField $integerField;

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

        $this->dataset = Dataset::create([
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $this->identifierField = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
        ]);

        $this->stringField = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'well_name',
            'display_name' => 'Well Name',
            'data_type' => 'string',
        ]);

        $this->integerField = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'depth',
            'display_name' => 'Depth',
            'data_type' => 'integer',
        ]);
    }

    // Authentication & Authorization Tests
    public function test_unauthenticated_user_cannot_list_imports(): void
    {
        $response = $this->getJson("/api/datasets/{$this->dataset->id}/imports");
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_import(): void
    {
        $response = $this->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => UploadedFile::fake()->create('test.csv', 100),
            'column_mapping' => ['well_id' => 'well_id', 'well_name' => 'well_name'],
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
        ])->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => UploadedFile::fake()->create('test.csv', 100),
            'column_mapping' => ['well_id' => 'well_id'],
        ]);

        $response->assertStatus(403);
    }

    // Import Tests
    public function test_user_with_create_permission_can_import_csv(): void
    {
        $csvContent = "well_id,well_name,depth\nW-001,Well One,100\nW-002,Well Two,200\n";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => $file,
            'column_mapping' => [
                'well_id' => 'well_id',
                'well_name' => 'well_name',
                'depth' => 'depth',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'dataset_id',
                'original_filename',
                'source_format',
                'imported_by',
                'started_at',
                'completed_at',
                'status',
                'total_rows',
                'successful_rows',
                'failed_rows',
                'error_summary',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals('test.csv', $response->json('original_filename'));
        $this->assertEquals('csv', $response->json('source_format'));
        $this->assertEquals('completed', $response->json('status'));
        $this->assertEquals(2, $response->json('total_rows'));
        $this->assertEquals(2, $response->json('successful_rows'));
        $this->assertEquals(0, $response->json('failed_rows'));

        $this->assertDatabaseHas('dataset_records', [
            'dataset_id' => $this->dataset->id,
            'identifier_value' => 'W-001',
        ]);
        $this->assertDatabaseHas('dataset_records', [
            'dataset_id' => $this->dataset->id,
            'identifier_value' => 'W-002',
        ]);
    }

    public function test_import_with_invalid_file_type_gets_422(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => $file,
            'column_mapping' => ['well_id' => 'well_id'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_import_missing_identifier_field(): void
    {
        $csvContent = "well_name,depth\nWell One,100\n";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => $file,
            'column_mapping' => [
                'well_name' => 'well_name',
                'depth' => 'depth',
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals('failed', $response->json('status'));
        $this->assertEquals(1, $response->json('failed_rows'));
    }

    public function test_import_duplicate_identifier_gets_error(): void
    {
        DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['well_id' => 'W-001', 'well_name' => 'Existing Well'],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);

        $csvContent = "well_id,well_name,depth\nW-001,Well One,100\n";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => $file,
            'column_mapping' => [
                'well_id' => 'well_id',
                'well_name' => 'well_name',
                'depth' => 'depth',
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals('failed', $response->json('status'));
        $this->assertEquals(1, $response->json('failed_rows'));
        $this->assertStringContainsString('already exists', $response->json('error_summary.0.error'));
    }

    public function test_import_type_conversion(): void
    {
        $csvContent = "well_id,well_name,depth\nW-001,Well One,100\nW-002,Well Two,200\n";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => $file,
            'column_mapping' => [
                'well_id' => 'well_id',
                'well_name' => 'well_name',
                'depth' => 'depth',
            ],
        ]);

        $response->assertStatus(201);

        $record = DatasetRecord::where('dataset_id', $this->dataset->id)
            ->where('identifier_value', 'W-001')
            ->first();

        $this->assertIsInt($record->values['depth']);
        $this->assertEquals(100, $record->values['depth']);
    }

    public function test_import_xlsx_works(): void
    {
        // Create a simple XLSX file using PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'well_id');
        $sheet->setCellValue('B1', 'well_name');
        $sheet->setCellValue('C1', 'depth');
        $sheet->setCellValue('A2', 'W-001');
        $sheet->setCellValue('B2', 'Well One');
        $sheet->setCellValue('C2', 100);
        $sheet->setCellValue('A3', 'W-002');
        $sheet->setCellValue('B3', 'Well Two');
        $sheet->setCellValue('C3', 200);

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $tempFile = sys_get_temp_dir() . '/test_import_' . uniqid() . '.xlsx';
        $writer->save($tempFile);

        $file = new \Illuminate\Http\UploadedFile(
            $tempFile,
            'test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/imports", [
            'file' => $file,
            'column_mapping' => [
                'well_id' => 'well_id',
                'well_name' => 'well_name',
                'depth' => 'depth',
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals('completed', $response->json('status'));
        $this->assertEquals(2, $response->json('total_rows'));
        $this->assertEquals(2, $response->json('successful_rows'));
        $this->assertEquals(0, $response->json('failed_rows'));

        $this->assertDatabaseHas('dataset_records', [
            'dataset_id' => $this->dataset->id,
            'identifier_value' => 'W-001',
        ]);
        $this->assertDatabaseHas('dataset_records', [
            'dataset_id' => $this->dataset->id,
            'identifier_value' => 'W-002',
        ]);

        @unlink($tempFile);
    }

    public function test_can_list_imports(): void
    {
        DatasetImport::create([
            'dataset_id' => $this->dataset->id,
            'original_filename' => 'import1.csv',
            'source_format' => 'csv',
            'imported_by' => $this->admin->id,
            'started_at' => now(),
            'completed_at' => now(),
            'status' => 'completed',
            'total_rows' => 10,
            'successful_rows' => 10,
            'failed_rows' => 0,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->dataset->id}/imports");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_filter_imports_by_status(): void
    {
        DatasetImport::create([
            'dataset_id' => $this->dataset->id,
            'original_filename' => 'import1.csv',
            'source_format' => 'csv',
            'imported_by' => $this->admin->id,
            'started_at' => now(),
            'completed_at' => now(),
            'status' => 'completed',
            'total_rows' => 10,
            'successful_rows' => 10,
            'failed_rows' => 0,
        ]);

        DatasetImport::create([
            'dataset_id' => $this->dataset->id,
            'original_filename' => 'import2.csv',
            'source_format' => 'csv',
            'imported_by' => $this->admin->id,
            'started_at' => now(),
            'status' => 'failed',
            'total_rows' => 5,
            'successful_rows' => 0,
            'failed_rows' => 5,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->dataset->id}/imports?status=completed");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('completed', $response->json('data.0.status'));
    }

    public function test_can_show_import(): void
    {
        $import = DatasetImport::create([
            'dataset_id' => $this->dataset->id,
            'original_filename' => 'test.csv',
            'source_format' => 'csv',
            'imported_by' => $this->admin->id,
            'started_at' => now(),
            'completed_at' => now(),
            'status' => 'completed',
            'total_rows' => 10,
            'successful_rows' => 10,
            'failed_rows' => 0,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->dataset->id}/imports/{$import->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'dataset_id',
                'original_filename',
                'source_format',
                'imported_by',
                'started_at',
                'completed_at',
                'status',
                'total_rows',
                'successful_rows',
                'failed_rows',
                'error_summary',
                'created_at',
                'updated_at',
            ]);
    }
}