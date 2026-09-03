<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['exists:roles,name'],
        ];
    }
}