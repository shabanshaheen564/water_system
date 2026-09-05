<?php

namespace App\Http\Requests\DatasetImport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatasetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,xlsx', 'max:10240'],
            'column_mapping' => ['required', 'array'],
            'column_mapping.*' => ['nullable', 'string'],
        ];
    }
}