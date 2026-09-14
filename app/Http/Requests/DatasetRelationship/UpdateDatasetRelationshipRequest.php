<?php

namespace App\Http\Requests\DatasetRelationship;

use App\Models\Dataset;
use App\Models\DatasetField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDatasetRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_dataset_id' => ['sometimes', 'required', Rule::exists('datasets', 'id')],
            'child_dataset_id' => ['sometimes', 'required', Rule::exists('datasets', 'id')],
            'parent_field_id' => ['sometimes', 'required', Rule::exists('dataset_fields', 'id')],
            'child_field_id' => ['sometimes', 'required', Rule::exists('dataset_fields', 'id')],
            'relationship_type' => ['sometimes', 'required', Rule::in(['one_to_many'])],
            'on_delete_behavior' => ['sometimes', 'required', Rule::in(['restrict', 'cascade', 'set_null'])],
            'is_nullable' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $parentDatasetId = $this->input('parent_dataset_id');
            $childDatasetId = $this->input('child_dataset_id');
            $parentFieldId = $this->input('parent_field_id');
            $childFieldId = $this->input('child_field_id');
            $onDeleteBehavior = $this->input('on_delete_behavior');
            $isNullable = $this->input('is_nullable');

            $relationship = $this->route('relationship');

            $effectiveParentDatasetId = $parentDatasetId ?? $relationship->parent_dataset_id;
            $effectiveChildDatasetId = $childDatasetId ?? $relationship->child_dataset_id;
            $effectiveParentFieldId = $parentFieldId ?? $relationship->parent_field_id;
            $effectiveChildFieldId = $childFieldId ?? $relationship->child_field_id;
            $effectiveOnDeleteBehavior = $onDeleteBehavior ?? $relationship->on_delete_behavior;
            $effectiveIsNullable = $isNullable ?? $relationship->is_nullable;

            // Self-dataset check: use effective IDs to catch changes where only one dataset ID is updated
            if ($effectiveParentDatasetId === $effectiveChildDatasetId) {
                $validator->errors()->add('child_dataset_id', 'Parent and child datasets must be different.');
            }

            if ($parentFieldId && $parentDatasetId) {
                $parentField = DatasetField::where('id', $parentFieldId)
                    ->where('dataset_id', $parentDatasetId)
                    ->first();

                if (!$parentField) {
                    $validator->errors()->add('parent_field_id', 'Parent field does not belong to parent dataset.');
                }
            } elseif ($parentFieldId) {
                $parentField = DatasetField::where('id', $parentFieldId)
                    ->where('dataset_id', $effectiveParentDatasetId)
                    ->first();

                if (!$parentField) {
                    $validator->errors()->add('parent_field_id', 'Parent field does not belong to parent dataset.');
                }
            }

            if ($childFieldId && $childDatasetId) {
                $childField = DatasetField::where('id', $childFieldId)
                    ->where('dataset_id', $childDatasetId)
                    ->first();

                if (!$childField) {
                    $validator->errors()->add('child_field_id', 'Child field does not belong to child dataset.');
                }
            } elseif ($childFieldId) {
                $childField = DatasetField::where('id', $childFieldId)
                    ->where('dataset_id', $effectiveChildDatasetId)
                    ->first();

                if (!$childField) {
                    $validator->errors()->add('child_field_id', 'Child field does not belong to child dataset.');
                }
            }

            if ($this->input('relationship_type') === 'one_to_many' || $relationship->relationship_type === 'one_to_many') {
                $parentField = DatasetField::find($effectiveParentFieldId);
                if ($parentField && !$parentField->is_identifier && !$parentField->is_unique) {
                    $validator->errors()->add('parent_field_id', 'For one_to_many relationship, parent field must be an identifier or unique field.');
                }
            }

            // set_null validation: reject if child field is required, regardless of is_nullable
            if ($effectiveOnDeleteBehavior === 'set_null') {
                $childField = DatasetField::find($effectiveChildFieldId);
                if ($childField && $childField->is_required) {
                    $validator->errors()->add('on_delete_behavior', 'Cannot use set_null when child field is required.');
                }
            }
        });
    }
}