<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Route parameter name is 'userId' (camelCase) from routes/web.php
        $userId = $this->route('userId');
        
        return [
            'identifier' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'identifier')->ignore($userId, 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId, 'id')->whereNull('deleted_at'),
            ],
            'jenis' => ['required', Rule::in(['bk', 'wali_kelas'])],
            'jenis_kelamin' => ['sometimes', Rule::in(['L', 'P'])],
        ];
    }
}
