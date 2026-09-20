<?php

namespace App\Http\Requests\GisFeature;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGisFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dataset = $this->route('dataset');
        $creatingRecord = !$this->filled('dataset_record_id');

        $hasFields = $dataset->fields()->exists();

        $rules = [
            'dataset_record_id' => [
                $hasFields ? 'required_without:values' : 'nullable',
                Rule::exists('dataset_records', 'id')->where('dataset_id', $dataset->id),
            ],
            'values' => [
                $hasFields ? 'required_without:dataset_record_id' : 'nullable',
                'array',
            ],
            'geometry' => ['required', 'array'],
            'geometry.type' => [
                'required',
                'string',
                Rule::in(config('gis.geometry_types')),
            ],
            'geometry.coordinates' => ['required', 'array'],
        ];

        foreach ($dataset->fields()->get() as $field) {
            $fieldRules = [$creatingRecord
                ? ($field->is_required ? 'required' : 'sometimes')
                : 'sometimes'];

            switch ($field->data_type) {
                case 'string':
                case 'text':
                    $fieldRules[] = 'string';
                    break;
                case 'integer':
                    $fieldRules[] = 'integer';
                    break;
                case 'decimal':
                    $fieldRules[] = 'numeric';
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'date':
                case 'datetime':
                    $fieldRules[] = 'date';
                    break;
            }

            if ($field->is_unique || $field->is_identifier) {
                $fieldRules[] = Rule::unique('dataset_records', 'values->' . $field->name)
                    ->where('dataset_id', $dataset->id);
            }

            $rules["values.{$field->name}"] = $fieldRules;
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $dataset = $this->route('dataset');

            if (!$dataset->isSpatial()) {
                $validator->errors()->add('dataset', 'This dataset is not configured as spatial.');
                return;
            }

            if ($dataset->geometry_type) {
                $geometryType = $this->input('geometry.type');
                if ($geometryType !== $dataset->geometry_type) {
                    $validator->errors()->add('geometry.type', "Geometry type must be {$dataset->geometry_type} for this dataset.");
                }
            }

            $geometry = $this->input('geometry');
            if ($geometry && isset($geometry['type'], $geometry['coordinates'])) {
                $this->validateGeometryCoordinates($validator, $geometry['type'], $geometry['coordinates']);
            }

            if (!$this->filled('dataset_record_id')) {
                $fields = $dataset->fields()->get();
                $values = $this->input('values', []);
                $knownFields = $fields->pluck('name')->toArray();
                $unknownFields = array_diff(array_keys($values), $knownFields);

                if ($unknownFields) {
                    $validator->errors()->add('values', 'Unknown fields: ' . implode(', ', $unknownFields));
                }

                $identifierField = $fields->where('is_identifier', true)->first();
                if ($identifierField && !array_key_exists($identifierField->name, $values)) {
                    $validator->errors()->add('values.' . $identifierField->name, 'Identifier field is required.');
                }
            }
        });
    }

    private function validateGeometryCoordinates($validator, string $type, array $coordinates): void
    {
        $invalid = match ($type) {
            'Point' => !$this->isCoordinateTuple($coordinates),
            'MultiPoint' => !$this->isCoordinateArray($coordinates),
            'LineString' => !$this->isLineStringCoordinates($coordinates),
            'MultiLineString' => !$this->isMultiLineStringCoordinates($coordinates),
            'Polygon' => !$this->isPolygonCoordinates($coordinates),
            'MultiPolygon' => !$this->isMultiPolygonCoordinates($coordinates),
            default => true,
        };

        if ($invalid) {
            $validator->errors()->add('geometry.coordinates', "Invalid coordinates for {$type}.");
        }
    }

    private function isCoordinateTuple(mixed $value): bool
    {
        if (!is_array($value) || count($value) < 2) return false;

        foreach ($value as $coordinate) {
            if (!is_int($coordinate) && !is_float($coordinate)) return false;
            if (!is_finite((float) $coordinate)) return false;
        }

        return true;
    }

    private function isCoordinateArray(array $coordinates): bool
    {
        if ($coordinates === []) return false;

        foreach ($coordinates as $point) {
            if (!$this->isCoordinateTuple($point)) return false;
        }

        return true;
    }

    private function isLineStringCoordinates(array $coordinates): bool
    {
        return count($coordinates) >= 2 && $this->isCoordinateArray($coordinates);
    }

    private function isMultiLineStringCoordinates(array $coordinates): bool
    {
        if ($coordinates === []) return false;

        foreach ($coordinates as $line) {
            if (!is_array($line) || !$this->isLineStringCoordinates($line)) return false;
        }

        return true;
    }

    private function isPolygonCoordinates(array $coordinates): bool
    {
        if ($coordinates === []) return false;

        foreach ($coordinates as $ring) {
            if (!is_array($ring) || count($ring) < 4 || !$this->isCoordinateArray($ring)) return false;
            if ($ring[0] !== $ring[array_key_last($ring)]) return false;
        }

        return true;
    }

    private function isMultiPolygonCoordinates(array $coordinates): bool
    {
        if ($coordinates === []) return false;

        foreach ($coordinates as $polygon) {
            if (!is_array($polygon) || !$this->isPolygonCoordinates($polygon)) return false;
        }

        return true;
    }
}
