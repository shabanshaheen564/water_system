<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetImport\StoreDatasetImportRequest;
use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetImport;
use App\Models\DatasetRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatasetImportController extends Controller
{
    public function index(Request $request, Dataset $dataset): JsonResponse
    {
        $query = DatasetImport::where('dataset_id', $dataset->id)
            ->with(['importedBy:id,name,email']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $imports = $query->orderBy('created_at', 'desc')->paginate();

        $data = $imports->getCollection()->map(function ($import) {
            return $this->formatImport($import);
        });

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $imports->url(1),
                'last' => $imports->url($imports->lastPage()),
                'prev' => $imports->previousPageUrl(),
                'next' => $imports->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $imports->currentPage(),
                'from' => $imports->firstItem(),
                'last_page' => $imports->lastPage(),
                'path' => $imports->path(),
                'per_page' => $imports->perPage(),
                'to' => $imports->lastItem(),
                'total' => $imports->total(),
            ],
        ]);
    }

    public function show(Dataset $dataset, DatasetImport $import): JsonResponse
    {
        $import->load(['importedBy:id,name,email']);

        return response()->json($this->formatImport($import));
    }

    public function store(StoreDatasetImportRequest $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');

        return DB::transaction(function () use ($validated, $file, $dataset, $request) {
            $import = DatasetImport::create([
                'dataset_id' => $dataset->id,
                'original_filename' => $file->getClientOriginalName(),
                'source_format' => $file->getClientOriginalExtension(),
                'imported_by' => $request->user()->id,
                'started_at' => now(),
                'status' => 'processing',
                'total_rows' => 0,
                'successful_rows' => 0,
                'failed_rows' => 0,
            ]);

            // Process file synchronously for now
            $this->processImport($import, $file, $validated['column_mapping'], $dataset);

            return response()->json($this->formatImport($import->fresh()), 201);
        });
    }

    private function processImport(DatasetImport $import, $file, array $columnMapping, Dataset $dataset): void
    {
        try {
            $extension = $file->getClientOriginalExtension();
            $rows = [];

            if ($extension === 'csv') {
                $rows = $this->parseCsv($file);
            } elseif ($extension === 'xlsx') {
                $rows = $this->parseXlsx($file);
            }

            $import->update([
                'total_rows' => count($rows),
                'status' => 'processing',
            ]);

            $successful = 0;
            $failed = 0;
            $errors = [];

            $fields = $dataset->fields()->get()->keyBy('name');
            $identifierField = $dataset->getIdentifierField();

            foreach ($rows as $index => $row) {
                try {
                    $values = [];

                    foreach ($columnMapping as $sourceColumn => $targetField) {
                        if ($targetField && isset($row[$sourceColumn]) && $fields->has($targetField)) {
                            $field = $fields[$targetField];
                            $values[$targetField] = $this->castValue($row[$sourceColumn], $field->data_type);
                        }
                    }

                    // Check identifier field
                    if ($identifierField && !isset($values[$identifierField->name])) {
                        throw new \Exception("Identifier field '{$identifierField->name}' is missing");
                    }

                    // Check unique fields
                    foreach ($fields as $fieldName => $field) {
                        if (($field->is_unique || $field->is_identifier) && isset($values[$fieldName])) {
                            $exists = DatasetRecord::where('dataset_id', $dataset->id)
                                ->whereJsonContains('values', [$fieldName => $values[$fieldName]])
                                ->exists();

                            if ($exists) {
                                throw new \Exception("Unique field '$fieldName' already exists with value: " . $values[$fieldName]);
                            }
                        }
                    }

                    $identifierValue = $identifierField ? ($values[$identifierField->name] ?? null) : null;

                    DatasetRecord::create([
                        'dataset_id' => $dataset->id,
                        'values' => $values,
                        'identifier_value' => $identifierValue,
                        'created_by' => $import->imported_by,
                    ]);

                    $successful++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage(),
                        'data' => $row,
                    ];
                }
            }

            $import->update([
                'status' => $failed > 0 && $successful === 0 ? 'failed' : ($failed > 0 ? 'completed' : 'completed'),
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'completed_at' => now(),
                'error_summary' => $errors,
            ]);
        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_summary' => [['error' => $e->getMessage()]],
            ]);
        }
    }

    private function parseCsv($file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \Exception('Could not open CSV file');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [];
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);
        return $rows;
    }

    private function parseXlsx($file): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];

        $headerRow = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
                $headerRow[] = $cell->getValue();
            }
        }

        if (empty($headerRow)) {
            return [];
        }

        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $data = [];
            foreach ($cellIterator as $cell) {
                $data[] = $cell->getValue();
            }

            if (count($data) !== count($headerRow)) {
                continue;
            }
            $rows[] = array_combine($headerRow, $data);
        }

        return $rows;
    }

    private function castValue(string $value, string $dataType): mixed
    {
        $value = trim($value);

        switch ($dataType) {
            case 'integer':
                return (int) $value;
            case 'decimal':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (strtolower($value) === 'true');
            case 'date':
            case 'datetime':
                return $value;
            case 'text':
            case 'string':
            default:
                return $value;
        }
    }

    private function formatImport(DatasetImport $import): array
    {
        return [
            'id' => $import->id,
            'dataset_id' => $import->dataset_id,
            'original_filename' => $import->original_filename,
            'source_format' => $import->source_format,
            'imported_by' => $import->importedBy ? [
                'id' => $import->importedBy->id,
                'name' => $import->importedBy->name,
                'email' => $import->importedBy->email,
            ] : null,
            'started_at' => $import->started_at?->toISOString(),
            'completed_at' => $import->completed_at?->toISOString(),
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->failed_rows,
            'error_summary' => $import->error_summary,
            'created_at' => $import->created_at?->toISOString(),
            'updated_at' => $import->updated_at?->toISOString(),
        ];
    }
}