<?php

namespace App\Http\Requests\DatasetField;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDatasetFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $field = $this->route('field');
        $datasetId = $field->dataset_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('dataset_fields', 'name')->where('dataset_id', $datasetId)->ignore($field->id)],
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'data_type' => ['sometimes', Rule::in(['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text'])],
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
            $field = $this->route('field');
            $datasetId = $field->dataset_id;

            if ($this->boolean('is_identifier') && !$field->is_identifier) {
                $existingIdentifier = \App\Models\DatasetField::where('dataset_id', $datasetId)
                    ->where('is_identifier', true)
                    ->where('id', '!=', $field->id)
                    ->exists();

                if ($existingIdentifier) {
                    $validator->errors()->add('is_identifier', 'Dataset already has an identifier field.');
                }
            }
        });
    }
}