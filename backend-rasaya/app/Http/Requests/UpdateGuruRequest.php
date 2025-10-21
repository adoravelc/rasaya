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
        $userId = $this->route('userId');
        return [
            'identifier' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'identifier')->ignore($userId)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'jenis' => ['required', Rule::in(['bk', 'wali_kelas'])],
        ];
    }
}
