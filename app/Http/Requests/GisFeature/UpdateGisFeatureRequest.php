<?php

namespace App\Http\Requests\GisFeature;

use App\Models\Dataset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGisFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dataset = $this->route('dataset');

        return [
            'geometry' => ['sometimes', 'array'],
            'geometry.type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(config('gis.geometry_types')),
            ],
            'geometry.coordinates' => ['sometimes', 'required', 'array'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $dataset = $this->route('dataset');
            $feature = $this->route('feature');

            if (!$dataset->isSpatial()) {
                $validator->errors()->add('dataset', 'This dataset is not configured as spatial.');
                return;
            }

            if ($dataset->geometry_type) {
                $geometryType = $this->input('geometry.type');
                if ($geometryType && $geometryType !== $dataset->geometry_type) {
                    $validator->errors()->add('geometry.type', "Geometry type must be {$dataset->geometry_type} for this dataset.");
                }
            }

            // Validate GeoJSON structure based on geometry type
            $geometry = $this->input('geometry');
            if ($geometry && isset($geometry['type'], $geometry['coordinates'])) {
                $this->validateGeometryCoordinates($validator, $geometry['type'], $geometry['coordinates']);
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
        if (!is_array($value) || count($value) < 2) {
            return false;
        }

        foreach ($value as $coordinate) {
            if (!is_int($coordinate) && !is_float($coordinate)) {
                return false;
            }

            if (!is_finite((float) $coordinate)) {
                return false;
            }
        }

        return true;
    }

    private function isCoordinateArray(array $coordinates): bool
    {
        if ($coordinates === []) {
            return false;
        }

        foreach ($coordinates as $point) {
            if (!$this->isCoordinateTuple($point)) {
                return false;
            }
        }

        return true;
    }

    private function isLineStringCoordinates(array $coordinates): bool
    {
        return count($coordinates) >= 2 && $this->isCoordinateArray($coordinates);
    }

    private function isMultiLineStringCoordinates(array $coordinates): bool
    {
        if ($coordinates === []) {
            return false;
        }

        foreach ($coordinates as $line) {
            if (!is_array($line) || !$this->isLineStringCoordinates($line)) {
                return false;
            }
        }

        return true;
    }

    private function isPolygonCoordinates(array $coordinates): bool
    {
        if ($coordinates === []) {
            return false;
        }

        foreach ($coordinates as $ring) {
            if (!is_array($ring) || count($ring) < 4 || !$this->isCoordinateArray($ring)) {
                return false;
            }

            if ($ring[0] !== $ring[array_key_last($ring)]) {
                return false;
            }
        }

        return true;
    }

    private function isMultiPolygonCoordinates(array $coordinates): bool
    {
        if ($coordinates === []) {
            return false;
        }

        foreach ($coordinates as $polygon) {
            if (!is_array($polygon) || !$this->isPolygonCoordinates($polygon)) {
                return false;
            }
        }

        return true;
    }
}