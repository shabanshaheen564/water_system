<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ];
    }
}