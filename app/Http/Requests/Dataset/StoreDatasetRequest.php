<?php

namespace App\Http\Requests\Dataset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:datasets,name', 'regex:/^[a-zA-Z0-9_]+$/'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'dataset_type' => ['required', Rule::in(['official_layer', 'additional_table'])],
            'source_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_format' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'is_spatial' => ['boolean'],
            'geometry_type' => ['sometimes', Rule::in(['Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'])],
            'srid' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $isSpatial = $this->boolean('is_spatial');
            $geometryType = $this->input('geometry_type');
            $srid = $this->input('srid');

            if ($isSpatial) {
                if (!$geometryType) {
                    $validator->errors()->add('geometry_type', 'Geometry type is required for spatial datasets.');
                }
                if (!$srid) {
                    $validator->errors()->add('srid', 'SRID is required for spatial datasets.');
                }
            } else {
                if ($geometryType || $srid) {
                    $validator->errors()->add('geometry_type', 'Geometry type and SRID must be null for non-spatial datasets.');
                    $validator->errors()->add('srid', 'Geometry type and SRID must be null for non-spatial datasets.');
                }
            }
        });
    }
}