<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\DatasetImport;
use App\Models\DatasetRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;

class DatasetImportService
{
    public function preview(UploadedFile $file, int $sampleRows = 10): array
    {
        $parsed = $this->parse($file);

        $sample = array_slice($parsed['rows'], 0, $sampleRows);
        $types = [];
        foreach ($parsed['headers'] as $header) {
            $values = array_map(
                fn (array $row) => $row['data'][$header] ?? null,
                array_slice($parsed['rows'], 0, 100)
            );
            $types[$header] = $this->inferDataType($values);
        }

        return [
            'headers' => $parsed['headers'],
            'sample_rows' => array_map(fn (array $row) => $row['data'], $sample),
            'total_rows' => count($parsed['rows']) + count($parsed['errors']),
            'parse_errors' => $parsed['errors'],
            'inferred_types' => $types,
        ];
    }

    public function import(DatasetImport $import, UploadedFile $file, array $columnMapping, Dataset $dataset): void
    {
        try {
            $parsed = $this->parse($file);
            $rows = $parsed['rows'];
            $errors = $parsed['errors'];
            $headers = $parsed['headers'];

            $import->update([
                'total_rows' => count($rows) + count($errors),
                'status' => 'processing',
            ]);

            $fields = $dataset->fields()->get()->keyBy('name');
            $this->validateColumnMapping($columnMapping, $headers, $fields);

            $successful = 0;
            $failed = count($errors);
            $seenUniqueValues = [];
            $identifierField = $dataset->getIdentifierField();

            foreach ($rows as $rowInfo) {
                try {
                    $values = [];

                    foreach ($columnMapping as $sourceColumn => $targetField) {
                        if ($targetField === null || $targetField === '') {
                            continue;
                        }

                        $field = $fields->get($targetField);
                        $values[$targetField] = $this->castValue(
                            $rowInfo['data'][$sourceColumn] ?? null,
                            $field->data_type
                        );
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
                        'row' => $rowInfo['row'],
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

    private function parse(UploadedFile $file): array
    {
        return match (strtolower($file->getClientOriginalExtension())) {
            'csv' => $this->parseCsv($file),
            'xlsx' => $this->parseXlsx($file),
            default => throw new \RuntimeException('Unsupported import format.'),
        };
    }

    private function parseCsv(UploadedFile $file): array
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

            $delimiter = $this->detectCsvDelimiter($firstLine);
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter);

            if ($headers === false) {
                return ['headers' => [], 'rows' => [], 'errors' => []];
            }

            $headers = array_map(
                fn ($header) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
                $headers
            );
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
                    $errors[] = ['row' => $rowNumber, 'error' => 'Column count does not match the header count'];
                    continue;
                }

                $rows[] = ['row' => $rowNumber, 'data' => array_combine($headers, $data)];
            }

            return ['headers' => $headers, 'rows' => $rows, 'errors' => $errors];
        } finally {
            fclose($handle);
        }
    }

    private function parseXlsx(UploadedFile $file): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        if ($highestRow < 1 || ($highestColumn === 'A' && $sheet->getCell('A1')->getValue() === null)) {
            return ['headers' => [], 'rows' => [], 'errors' => []];
        }

        $headers = $sheet->rangeToArray("A1:{$highestColumn}1", null, false, false, false)[0] ?? [];
        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        $this->validateHeaders($headers);

        $rows = [];
        $errors = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $data = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, false, false, false)[0] ?? [];

            if ($this->isEmptyXlsxRow($data)) {
                continue;
            }

            if (count($data) !== count($headers)) {
                $errors[] = ['row' => $rowNumber, 'error' => 'Column count does not match the header count'];
                continue;
            }

            $rows[] = ['row' => $rowNumber, 'data' => array_combine($headers, $data)];
        }

        return ['headers' => $headers, 'rows' => $rows, 'errors' => $errors];
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

    private function validateColumnMapping(array $mapping, array $headers, $fields): void
    {
        $headerSet = array_fill_keys($headers, true);
        $mappedTargets = [];

        foreach ($mapping as $sourceColumn => $targetField) {
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
            if ($dataType === 'date') return $value->format('Y-m-d');
            if ($dataType === 'datetime') return $value->format('Y-m-d H:i:s');
        }

        if ($value === null) return null;

        $value = trim((string) $value);
        if ($value === '') return null;

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
            default => throw new \RuntimeException("Invalid boolean value '$value'"),
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
        $best = ',';
        $count = 0;
        foreach ([',', ';', "\t"] as $delimiter) {
            $current = count(str_getcsv($line, $delimiter));
            if ($current > $count) {
                $count = $current;
                $best = $delimiter;
            }
        }
        return $best;
    }

    private function isEmptyCsvRow(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') return false;
        }
        return true;
    }

    private function isEmptyXlsxRow(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null && trim((string) $value) !== '') return false;
        }
        return true;
    }

    private function uniqueValueKey(string $fieldName, mixed $value): string
    {
        return $fieldName . ':' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function inferDataType(array $values): string
    {
        $nonEmpty = array_values(array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== ''));
        if ($nonEmpty === []) return 'string';

        $allInteger = true;
        $allDecimal = true;
        foreach ($nonEmpty as $value) {
            $string = trim((string) $value);
            if (!preg_match('/^-?\d+$/', $string)) $allInteger = false;
            if (!preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $string)) $allDecimal = false;
        }

        if ($allInteger) return 'integer';
        if ($allDecimal) return 'decimal';

        $lower = array_map(fn ($value) => strtolower(trim((string) $value)), $nonEmpty);
        if (count(array_diff($lower, ['true', 'false', '1', '0', 'yes', 'no', 'y', 'n', 'on', 'off'])) === 0) {
            return 'boolean';
        }

        $allDates = true;
        foreach ($nonEmpty as $value) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $value));
            $errors = DateTimeImmutable::getLastErrors();
            if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                $allDates = false;
                break;
            }
        }
        if ($allDates) return 'date';

        return 'string';
    }
}
