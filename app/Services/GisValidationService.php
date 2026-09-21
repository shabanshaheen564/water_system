<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use Illuminate\Support\Facades\DB;

class GisValidationService
{
    public function validate(Dataset $dataset): array
    {
        $records = DatasetRecord::query()
            ->where('dataset_id', $dataset->id)
            ->get(['id', 'values', 'identifier_value']);

        $features = $dataset->isSpatial()
            ? collect(DB::select(
                'SELECT id, dataset_record_id, ST_SRID(geometry) AS actual_srid,
                        ST_GeometryType(geometry) AS actual_geometry_type,
                        ST_IsValid(geometry) AS is_valid,
                        ST_IsValidReason(geometry) AS validity_reason,
                        ST_IsEmpty(geometry) AS is_empty,
                        encode(ST_AsEWKB(geometry), \'hex\') AS geometry_hash
                 FROM gis_features
                 WHERE dataset_id = ?
                 ORDER BY id',
                [$dataset->id]
            ))
            : collect();

        $recordById = $records->keyBy('id');
        $featureByRecord = $features->keyBy('dataset_record_id');

        $expectedGeometryType = $dataset->geometry_type ? 'ST_'.$dataset->geometry_type : null;

        $validGeometry = 0;
        $invalidGeometry = 0;
        $missingGeometry = 0;
        $emptyGeometry = 0;
        $sridErrors = 0;
        $geometryTypeErrors = 0;
        $details = [];

        if ($dataset->isSpatial()) {
            foreach ($records as $record) {
                $feature = $featureByRecord->get($record->id);

                if (!$feature) {
                    ++$missingGeometry;
                    $this->addDetail($details, $record->id, null, 'missing_geometry', 'Geometry is missing.');
                    continue;
                }

                if ((bool) $feature->is_empty) {
                    ++$emptyGeometry;
                    $this->addDetail($details, $record->id, $feature->id, 'empty_geometry', 'Geometry is empty.');
                }

                if (!(bool) $feature->is_valid) {
                    ++$invalidGeometry;
                    $this->addDetail(
                        $details,
                        $record->id,
                        $feature->id,
                        'invalid_geometry',
                        (string) ($feature->validity_reason ?: 'Geometry is invalid.')
                    );
                } elseif (!(bool) $feature->is_empty) {
                    ++$validGeometry;
                }

                if ((int) $feature->actual_srid !== (int) $dataset->srid) {
                    ++$sridErrors;
                    $this->addDetail(
                        $details,
                        $record->id,
                        $feature->id,
                        'srid_mismatch',
                        'Expected SRID '.$dataset->srid.', actual SRID '.$feature->actual_srid.'.'
                    );
                }

                if ($expectedGeometryType && $feature->actual_geometry_type !== $expectedGeometryType) {
                    ++$geometryTypeErrors;
                    $this->addDetail(
                        $details,
                        $record->id,
                        $feature->id,
                        'geometry_type_mismatch',
                        'Expected '.$expectedGeometryType.', actual '.$feature->actual_geometry_type.'.'
                    );
                }
            }
        }

        $attributeErrors = 0;
        $duplicateIdentifierRecords = 0;
        $duplicateGeometryRecords = 0;

        foreach ($dataset->fields()->orderBy('sort_order')->get() as $field) {
            $seen = [];

            foreach ($records as $record) {
                $values = is_array($record->values) ? $record->values : [];
                $value = $values[$field->name] ?? null;

                if ($field->is_required && ($value === null || $value === '')) {
                    ++$attributeErrors;
                    $this->addDetail($details, $record->id, $featureByRecord->get($record->id)?->id, 'required_field', 'Required field '.$field->name.' is empty.', $field->name);
                }

                if ($value !== null && $value !== '' && !$this->matchesType($value, $field->data_type)) {
                    ++$attributeErrors;
                    $this->addDetail($details, $record->id, $featureByRecord->get($record->id)?->id, 'invalid_attribute_type', 'Field '.$field->name.' does not match type '.$field->data_type.'.', $field->name);
                }

                if ($field->is_unique && $value !== null && $value !== '') {
                    $key = (string) $value;
                    if (isset($seen[$key])) {
                        ++$attributeErrors;
                        $this->addDetail($details, $record->id, $featureByRecord->get($record->id)?->id, 'duplicate_attribute', 'Duplicate value in unique field '.$field->name.'.', $field->name);
                    } else {
                        $seen[$key] = $record->id;
                    }
                }
            }
        }

        $identifierField = $dataset->getIdentifierField();
        if ($identifierField) {
            $seen = [];
            foreach ($records as $record) {
                $value = $record->identifier_value;
                if ($value === null || $value === '') {
                    continue;
                }
                $key = (string) $value;
                if (isset($seen[$key])) {
                    ++$duplicateIdentifierRecords;
                    $this->addDetail($details, $record->id, $featureByRecord->get($record->id)?->id, 'duplicate_identifier', 'Duplicate identifier value '.$key.'.', $identifierField->name);
                } else {
                    $seen[$key] = $record->id;
                }
            }
        }

        if ($dataset->isSpatial()) {
            $groups = $features->filter(fn ($feature) => $feature->geometry_hash !== null)
                ->groupBy('geometry_hash');

            foreach ($groups as $hash => $group) {
                if ($group->count() < 2) {
                    continue;
                }

                foreach ($group->skip(1) as $feature) {
                    ++$duplicateGeometryRecords;
                    $this->addDetail(
                        $details,
                        $recordById->get($feature->dataset_record_id)?->id,
                        $feature->id,
                        'duplicate_geometry',
                        'Geometry duplicates another feature in this dataset.'
                    );
                }
            }
        }

        $total = $records->count();
        $errorCount = $invalidGeometry + $missingGeometry + $emptyGeometry + $sridErrors
            + $geometryTypeErrors + $attributeErrors + $duplicateIdentifierRecords + $duplicateGeometryRecords;

        return [
            'dataset' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'display_name' => $dataset->display_name,
                'is_spatial' => (bool) $dataset->is_spatial,
                'geometry_type' => $dataset->geometry_type,
                'srid' => $dataset->srid,
            ],
            'summary' => [
                'total_records' => $total,
                'valid_geometry' => $validGeometry,
                'invalid_geometry' => $invalidGeometry,
                'missing_geometry' => $missingGeometry,
                'empty_geometry' => $emptyGeometry,
                'srid_errors' => $sridErrors,
                'geometry_type_errors' => $geometryTypeErrors,
                'attribute_errors' => $attributeErrors,
                'duplicate_identifiers' => $duplicateIdentifierRecords,
                'duplicate_geometries' => $duplicateGeometryRecords,
                'error_count' => $errorCount,
            ],
            'details' => array_slice($details, 0, 500),
        ];
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'decimal' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            'boolean' => is_bool($value),
            'date' => is_string($value) && strtotime($value) !== false,
            'string' => is_string($value),
            default => true,
        };
    }

    private function addDetail(array &$details, ?int $recordId, ?int $featureId, string $type, string $message, ?string $field = null): void
    {
        if (count($details) >= 500) {
            return;
        }

        $details[] = [
            'record_id' => $recordId,
            'feature_id' => $featureId,
            'type' => $type,
            'field' => $field,
            'message' => $message,
        ];
    }
}
