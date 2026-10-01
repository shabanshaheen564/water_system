<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.inspect') ?? false;
    }

    public function rules(): array
    {
        return [
            'gis_feature_id' => ['required', 'integer', 'exists:gis_features,id'],
            'inspection_at' => ['nullable', 'date'],
            'result' => ['required', Rule::in(['okay', 'problem'])],
            'problem_description' => ['nullable', 'string', 'required_if:result,problem'],
            'notes' => ['nullable', 'string'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
