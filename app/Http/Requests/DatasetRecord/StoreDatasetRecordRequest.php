<?php

namespace App\Http\Requests\DatasetRecord;

use App\Models\Dataset;
use App\Models\DatasetField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatasetRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dataset = $this->route('dataset');
        $fields = $dataset->fields()->get();

        $rules = [
            'values' => ['required', 'array'],
        ];

        foreach ($fields as $field) {
            $fieldName = $field->name;
            $fieldRules = [];

            if ($field->is_required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'sometimes';
            }

            // Type validation
            switch ($field->data_type) {
                case 'string':
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
                    $fieldRules[] = 'date';
                    break;
                case 'datetime':
                    $fieldRules[] = 'date';
                    break;
                case 'text':
                    $fieldRules[] = 'string';
                    break;
            }

            // Unique validation
            if ($field->is_unique || $field->is_identifier) {
                $fieldRules[] = Rule::unique('dataset_records', 'values->' . $fieldName)
                    ->where('dataset_id', $dataset->id);
            }

            $rules["values.$fieldName"] = $fieldRules;
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $dataset = $this->route('dataset');
            $fields = $dataset->fields()->get();
            $values = $this->input('values', []);

            // Check for unknown fields
            $knownFields = $fields->pluck('name')->toArray();
            $unknownFields = array_diff(array_keys($values), $knownFields);

            if (!empty($unknownFields)) {
                $validator->errors()->add('values', 'Unknown fields: ' . implode(', ', $unknownFields));
            }

            // Check identifier field exists
            $identifierField = $fields->where('is_identifier', true)->first();
            if ($identifierField && !isset($values[$identifierField->name])) {
                $validator->errors()->add('values.' . $identifierField->name, 'Identifier field is required.');
            }
        });
    }
}