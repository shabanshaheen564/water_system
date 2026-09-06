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
                Rule::in(['Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon']),
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
        switch ($type) {
            case 'Point':
                if (!is_array($coordinates) || count($coordinates) < 2) {
                    $validator->errors()->add('geometry.coordinates', 'Point coordinates must be an array of [longitude, latitude].');
                }
                break;
            case 'MultiPoint':
                if (!is_array($coordinates) || empty($coordinates)) {
                    $validator->errors()->add('geometry.coordinates', 'MultiPoint coordinates must be a non-empty array of points.');
                } elseif (isset($coordinates[0]) && (!is_array($coordinates[0]) || count($coordinates[0]) < 2)) {
                    $validator->errors()->add('geometry.coordinates', 'Each point in MultiPoint must be an array of [longitude, latitude].');
                }
                break;
            case 'LineString':
                if (!is_array($coordinates) || count($coordinates) < 2) {
                    $validator->errors()->add('geometry.coordinates', 'LineString must have at least 2 points.');
                }
                break;
            case 'MultiLineString':
                if (!is_array($coordinates) || empty($coordinates)) {
                    $validator->errors()->add('geometry.coordinates', 'MultiLineString must be a non-empty array of LineStrings.');
                }
                break;
            case 'Polygon':
                if (!is_array($coordinates) || empty($coordinates)) {
                    $validator->errors()->add('geometry.coordinates', 'Polygon must be an array of linear rings.');
                } elseif (isset($coordinates[0]) && (!is_array($coordinates[0]) || count($coordinates[0]) < 4)) {
                    $validator->errors()->add('geometry.coordinates', 'Polygon exterior ring must have at least 4 points (first = last).');
                }
                break;
            case 'MultiPolygon':
                if (!is_array($coordinates) || empty($coordinates)) {
                    $validator->errors()->add('geometry.coordinates', 'MultiPolygon must be a non-empty array of Polygons.');
                }
                break;
        }
    }
}