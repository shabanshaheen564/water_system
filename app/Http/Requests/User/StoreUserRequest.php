<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->filled('username')) {
            return;
        }

        $email = (string) $this->input('email', '');
        $base = Str::lower(Str::before($email, '@'));
        $base = (string) Str::of($base)->replaceMatches('/[^A-Za-z0-9._-]+/', '')->substr(0, 80);
        $base = strlen($base) >= 3 ? $base : 'user';

        $username = $base;
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $counter++;
            $username = substr($base, 0, 90 - strlen((string) $counter)) . $counter;
        }

        $this->merge(['username' => $username]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['exists:roles,name'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
