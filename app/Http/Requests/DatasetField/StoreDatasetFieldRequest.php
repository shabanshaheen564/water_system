<?php

namespace App\Http\Requests\DatasetField;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatasetFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/'],
            'display_name' => ['required', 'string', 'max:255'],
            'data_type' => ['required', Rule::in(['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text'])],
            'is_required' => ['sometimes', 'boolean'],
            'is_unique' => ['sometimes', 'boolean'],
            'is_identifier' => ['sometimes', 'boolean'],
            'default_value' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $datasetId = $this->route('dataset')->id;
            $dataset = \App\Models\Dataset::find($datasetId);

            if ($this->boolean('is_identifier')) {
                $existingIdentifier = \App\Models\DatasetField::where('dataset_id', $datasetId)
                    ->where('is_identifier', true)
                    ->exists();

                if ($existingIdentifier) {
                    $validator->errors()->add('is_identifier', 'Dataset already has an identifier field.');
                }
            }
        });
    }
}