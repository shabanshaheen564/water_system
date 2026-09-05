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
        $dataset = $this->route('dataset');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('datasets', 'name')->ignore($dataset->id)],
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'dataset_type' => ['sometimes', Rule::in(['official_layer', 'additional_table'])],
            'source_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_format' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}