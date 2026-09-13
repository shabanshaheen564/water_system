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
use DateTimeImmutable;
use DateTimeInterface;

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
        abort_unless($import->dataset_id === $dataset->id, 404);

        $import->load(['importedBy:id,name,email']);

        return response()->json($this->formatImport($import));
    }

    public function store(StoreDatasetImportRequest $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');

        $import = DatasetImport::create([
            'dataset_id' => $dataset->id,
            'original_filename' => $file->getClientOriginalName(),
            'source_format' => strtolower($file->getClientOriginalExtension()),
            'imported_by' => $request->user()->id,
            'started_at' => now(),
            'status' => 'processing',
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ]);

        // Processing is intentionally outside one global transaction so valid rows are
        // retained when other rows fail or the parser reports row-level problems.
        $this->processImport($import, $file, $validated['column_mapping'], $dataset);

        return response()->json($this->formatImport($import->fresh()), 201);
    }

    private function processImport(DatasetImport $import, $file, array $columnMapping, Dataset $dataset): void
    {
        try {
            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension === 'csv') {
                $parsed = $this->parseCsv($file);
            } elseif ($extension === 'xlsx') {
                $parsed = $this->parseXlsx($file);
            } else {
                throw new \RuntimeException('Unsupported import format.');
            }

            $rows = $parsed['rows'];
            $errors = $parsed['errors'];
            $headers = $parsed['headers'];

            $fields = $dataset->fields()->get()->keyBy('name');
            $this->validateColumnMapping($columnMapping, $headers, $fields);

            $import->update([
                'total_rows' => count($rows) + count($errors),
                'status' => 'processing',
            ]);

            $successful = 0;
            $failed = count($errors);
            $seenUniqueValues = [];
            $identifierField = $dataset->getIdentifierField();

            foreach ($rows as $rowInfo) {
                $rowNumber = $rowInfo['row'];
                $row = $rowInfo['data'];

                try {
                    $values = [];

                    foreach ($columnMapping as $sourceColumn => $targetField) {
                        if ($targetField === null || $targetField === '') {
                            continue;
                        }

                        $field = $fields->get($targetField);
                        $rawValue = $row[$sourceColumn] ?? null;
                        $values[$targetField] = $this->castValue($rawValue, $field->data_type);
                    }

                    $values = $this->applyDefaults($dataset, $values);
                    $this->validateRequiredFields($fields, $values);

                    if ($identifierField && !array_key_exists($identifierField->name, $values)) {
                        throw new \RuntimeException("Identifier field '{$identifierField->name}' is missing");
                    }

                    foreach ($fields as $fieldName => $field) {
                        if (!($field->is_unique || $field->is_identifier)) {
                            continue;
                        }

                        if (!array_key_exists($fieldName, $values) || $values[$fieldName] === null) {
                            continue;
                        }

                        $valueKey = $this->uniqueValueKey($fieldName, $values[$fieldName]);
                        if (isset($seenUniqueValues[$valueKey])) {
                            throw new \RuntimeException("Unique field '$fieldName' is duplicated within this import file");
                        }

                        $exists = DatasetRecord::where('dataset_id', $dataset->id)
                            ->whereJsonContains('values', [$fieldName => $values[$fieldName]])
                            ->exists();

                        if ($exists) {
                            throw new \RuntimeException("Unique field '$fieldName' already exists with value: " . $values[$fieldName]);
                        }

                        $seenUniqueValues[$valueKey] = true;
                    }

                    $identifierValue = $identifierField ? ($values[$identifierField->name] ?? null) : null;

                    DatasetRecord::create([
                        'dataset_id' => $dataset->id,
                        'values' => $values,
                        'identifier_value' => $identifierValue !== null ? (string) $identifierValue : null,
                        'created_by' => $import->imported_by,
                    ]);

                    $successful++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $status = match (true) {
                $successful > 0 && $failed > 0 => 'partial',
                $successful > 0 => 'completed',
                default => 'failed',
            };

            $import->update([
                'status' => $status,
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'completed_at' => now(),
                'error_summary' => $errors ?: null,
            ]);
        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'completed_at' => now(),
                'successful_rows' => 0,
                'failed_rows' => $import->total_rows,
                'error_summary' => [['error' => $e->getMessage()]],
            ]);
        }
    }

    private function parseCsv($file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            throw new \RuntimeException('Could not open CSV file');
        }

        try {
            $firstLine = null;
            while (($line = fgets($handle)) !== false) {
                if (trim($line) !== '') {
                    $firstLine = $line;
                    break;
                }
            }

            if ($firstLine === null) {
                return ['headers' => [], 'rows' => [], 'errors' => []];
            }

            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
            $delimiter = $this->detectCsvDelimiter($firstLine);
            rewind($handle);

            $headers = fgetcsv($handle, 0, $delimiter);
            if ($headers === false) {
                return ['headers' => [], 'rows' => [], 'errors' => []];
            }

            $headers = array_map(function ($header) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header));
            }, $headers);
            $this->validateHeaders($headers);

            $rows = [];
            $errors = [];
            $rowNumber = 1;

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($this->isEmptyCsvRow($data)) {
                    continue;
                }

                if (count($data) !== count($headers)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'error' => 'Column count does not match the header count',
                    ];
                    continue;
                }

                $rows[] = [
                    'row' => $rowNumber,
                    'data' => array_combine($headers, $data),
                ];
            }

            return ['headers' => $headers, 'rows' => $rows, 'errors' => $errors];
        } finally {
            fclose($handle);
        }
    }

    private function parseXlsx($file): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        if ($highestRow < 1 || $highestColumn === 'A' && $sheet->getCell('A1')->getValue() === null) {
            return ['headers' => [], 'rows' => [], 'errors' => []];
        }

        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, false, false, false)[0] ?? [];
        $headerRow = array_map(fn ($header) => trim((string) $header), $headerRow);
        $this->validateHeaders($headerRow);

        $rows = [];
        $errors = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $data = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, false, false, false)[0] ?? [];

            if ($this->isEmptyXlsxRow($data)) {
                continue;
            }

            if (count($data) !== count($headerRow)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'error' => 'Column count does not match the header count',
                ];
                continue;
            }

            $rows[] = [
                'row' => $rowNumber,
                'data' => array_combine($headerRow, $data),
            ];
        }

        return ['headers' => $headerRow, 'rows' => $rows, 'errors' => $errors];
    }

    private function validateHeaders(array $headers): void
    {
        foreach ($headers as $header) {
            if ($header === '') {
                throw new \RuntimeException('Import file contains an empty column header');
            }
        }

        $duplicates = array_keys(array_filter(array_count_values($headers), fn ($count) => $count > 1));
        if ($duplicates !== []) {
            throw new \RuntimeException('Import file contains duplicate column headers: ' . implode(', ', $duplicates));
        }
    }

    private function validateColumnMapping(array $columnMapping, array $headers, $fields): void
    {
        $headerSet = array_fill_keys($headers, true);
        $mappedTargets = [];

        foreach ($columnMapping as $sourceColumn => $targetField) {
            if ($targetField === null || $targetField === '') {
                continue;
            }

            if (!isset($headerSet[$sourceColumn])) {
                throw new \RuntimeException("Source column '$sourceColumn' does not exist in the import file");
            }

            if (!$fields->has($targetField)) {
                throw new \RuntimeException("Target field '$targetField' does not belong to the selected dataset");
            }

            if (isset($mappedTargets[$targetField])) {
                throw new \RuntimeException("Target field '$targetField' is mapped more than once");
            }

            $mappedTargets[$targetField] = true;
        }

        $identifierField = $fields->first(fn (DatasetField $field) => $field->is_identifier);
        if ($identifierField && !isset($mappedTargets[$identifierField->name]) && $identifierField->default_value === null) {
            throw new \RuntimeException("Identifier field '{$identifierField->name}' must be mapped");
        }
    }

    private function validateRequiredFields($fields, array $values): void
    {
        foreach ($fields as $field) {
            if (!$field->is_required) {
                continue;
            }

            if (!array_key_exists($field->name, $values) || $values[$field->name] === null || $values[$field->name] === '') {
                throw new \RuntimeException("Required field '{$field->name}' is missing");
            }
        }
    }

    private function applyDefaults(Dataset $dataset, array $values): array
    {
        foreach ($dataset->fields as $field) {
            if (!array_key_exists($field->name, $values) && $field->default_value !== null) {
                $values[$field->name] = $field->default_value;
            }
        }

        return $values;
    }

    private function castValue(mixed $value, string $dataType): mixed
    {
        if ($value instanceof DateTimeInterface) {
            if ($dataType === 'date') {
                return $value->format('Y-m-d');
            }
            if ($dataType === 'datetime') {
                return $value->format('Y-m-d H:i:s');
            }
        }

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($dataType) {
            'integer' => $this->castInteger($value),
            'decimal' => $this->castDecimal($value),
            'boolean' => $this->castBoolean($value),
            'date' => $this->castDate($value),
            'datetime' => $this->castDateTime($value),
            'text', 'string' => $value,
            default => $value,
        };
    }

    private function castInteger(string $value): int
    {
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new \RuntimeException("Invalid integer value '$value'");
        }

        return (int) $value;
    }

    private function castDecimal(string $value): float
    {
        if (!preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $value)) {
            throw new \RuntimeException("Invalid decimal value '$value'");
        }

        return (float) $value;
    }

    private function castBoolean(string $value): bool
    {
        return match (strtolower($value)) {
            'true', '1', 'yes', 'y', 'on' => true,
            'false', '0', 'no', 'n', 'off' => false,
            default => throw new \RuntimeException("Invalid boolean value '$value'")
        };
    }

    private function castDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new \RuntimeException("Invalid date value '$value'. Expected YYYY-MM-DD");
        }

        return $date->format('Y-m-d');
    }

    private function castDateTime(string $value): string
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', DATE_ATOM] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new \RuntimeException("Invalid datetime value '$value'");
    }

    private function detectCsvDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t"];
        $bestDelimiter = ',';
        $bestCount = 0;

        foreach ($candidates as $delimiter) {
            $count = count(str_getcsv($line, $delimiter));
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function isEmptyCsvRow(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isEmptyXlsxRow(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function uniqueValueKey(string $fieldName, mixed $value): string
    {
        return $fieldName . ':' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
