<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiswaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        // Convert empty password to null so it's truly optional
        if ($this->password === '') {
            $this->merge(['password' => null]);
        }
    }

    public function rules(): array
    {
        // Route parameter name is 'userId' (camelCase) from routes/web.php
        $userId = $this->route('userId');
        
        return [
            'identifier' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'identifier')->ignore($userId, 'id')->whereNull('deleted_at')
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId, 'id')->whereNull('deleted_at')
            ],
            'password' => ['nullable', 'min:6'],
        ];
    }
}
