<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protected by middleware
    }

    public function rules(): array
    {
        return [
            'identifier' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'identifier'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email'),
            ],
            'jenis' => ['required', Rule::in(['bk', 'wali_kelas'])],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
        ];
    }
}
