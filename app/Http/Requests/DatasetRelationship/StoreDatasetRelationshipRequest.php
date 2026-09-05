<?php

namespace App\Http\Requests\DatasetRelationship;

use App\Models\Dataset;
use App\Models\DatasetField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatasetRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_dataset_id' => ['required', Rule::exists('datasets', 'id')],
            'child_dataset_id' => ['required', Rule::exists('datasets', 'id'), 'different:parent_dataset_id'],
            'parent_field_id' => ['required', Rule::exists('dataset_fields', 'id')],
            'child_field_id' => ['required', Rule::exists('dataset_fields', 'id')],
            'relationship_type' => ['required', Rule::in(['one_to_many'])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $parentDatasetId = $this->input('parent_dataset_id');
            $childDatasetId = $this->input('child_dataset_id');
            $parentFieldId = $this->input('parent_field_id');
            $childFieldId = $this->input('child_field_id');

            // Validate parent field belongs to parent dataset
            if ($parentFieldId && $parentDatasetId) {
                $parentField = DatasetField::where('id', $parentFieldId)
                    ->where('dataset_id', $parentDatasetId)
                    ->first();

                if (!$parentField) {
                    $validator->errors()->add('parent_field_id', 'Parent field does not belong to parent dataset.');
                }
            }

            // Validate child field belongs to child dataset
            if ($childFieldId && $childDatasetId) {
                $childField = DatasetField::where('id', $childFieldId)
                    ->where('dataset_id', $childDatasetId)
                    ->first();

                if (!$childField) {
                    $validator->errors()->add('child_field_id', 'Child field does not belong to child dataset.');
                }
            }

            // For one_to_many, parent field should be identifier or unique
            if ($this->input('relationship_type') === 'one_to_many' && $parentFieldId) {
                $parentField = DatasetField::find($parentFieldId);
                if ($parentField && !$parentField->is_identifier && !$parentField->is_unique) {
                    $validator->errors()->add('parent_field_id', 'For one_to_many relationship, parent field must be an identifier or unique field.');
                }
            }
        });
    }
}