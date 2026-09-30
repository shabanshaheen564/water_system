<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.complete') === true;
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'diagnosed_fault' => ['nullable', 'string', 'max:5000'],
            'repair_action' => ['nullable', 'string', 'max:5000'],
            'materials_used' => ['nullable', 'string', 'max:5000'],
            'result' => ['required', Rule::in(['repaired', 'not_repaired', 'inspection_only'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
