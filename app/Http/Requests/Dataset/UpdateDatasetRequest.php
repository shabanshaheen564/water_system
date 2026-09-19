<?php

namespace App\Http\Requests\Dataset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'dataset_type' => ['sometimes', Rule::in(['official_layer', 'additional_table'])],
            'management_mode' => ['sometimes', Rule::in(['official', 'web_editable', 'operational', 'analytical'])],
            'source_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_format' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'is_spatial' => ['boolean'],
            'geometry_type' => ['sometimes', Rule::in(config('gis.geometry_types'))],
            'srid' => ['sometimes', 'nullable', 'integer', Rule::exists('spatial_ref_sys', 'srid')],
            'map_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'default_visible' => ['sometimes', 'boolean'],
            'map_opacity' => ['sometimes', 'numeric', 'between:0,1'],
            'display_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $dataset = $this->route('dataset');
            $isSpatial = $this->boolean('is_spatial', $dataset->is_spatial);
            $geometryType = $this->input('geometry_type', $dataset->geometry_type);
            $srid = $this->input('srid', $dataset->srid);
            $managementMode = $this->input('management_mode', $dataset->management_mode);

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

            if ($managementMode === 'web_editable' && !$isSpatial) {
                $validator->errors()->add('management_mode', 'Web editable datasets must be spatial.');
            }
        });
    }
}