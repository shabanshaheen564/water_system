<?php

namespace App\Http\Requests\DatasetRecord;

use App\Models\Dataset;
use App\Models\DatasetField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDatasetRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->route('record');
        $dataset = $record->dataset;
        $fields = $dataset->fields()->get();

        $rules = [
            'values' => ['sometimes', 'array'],
        ];

        foreach ($fields as $field) {
            $fieldName = $field->name;
            $fieldRules = ['sometimes'];

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

            // Unique validation - ignore current record
            if ($field->is_unique || $field->is_identifier) {
                $fieldRules[] = Rule::unique('dataset_records', 'values->' . $fieldName)
                    ->where('dataset_id', $dataset->id)
                    ->ignore($record->id);
            }

            $rules["values.$fieldName"] = $fieldRules;
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $record = $this->route('record');
            $dataset = $record->dataset;
            $fields = $dataset->fields()->get();
            $values = $this->input('values', []);

            // Check for unknown fields
            $knownFields = $fields->pluck('name')->toArray();
            $unknownFields = array_diff(array_keys($values), $knownFields);

            if (!empty($unknownFields)) {
                $validator->errors()->add('values', 'Unknown fields: ' . implode(', ', $unknownFields));
            }
        });
    }
}