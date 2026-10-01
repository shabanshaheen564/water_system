<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.update') === true;
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'problem_description' => ['required', 'string', 'max:5000'],
            'fault_description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['new', 'assigned', 'in_progress', 'waiting', 'completed', 'not_repaired', 'cancelled'])],
            'cancellation_reason' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
