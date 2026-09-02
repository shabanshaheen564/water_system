<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                function ($attribute, $value, $fail) {
                    $user = $this->user();
                    if (\Illuminate\Support\Facades\Hash::check($value, $user->password)) {
                        $fail('The new password must be different from the current password.');
                    }
                },
            ],
            'new_password_confirmation' => ['required', 'string'],
        ];
    }
}