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
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}