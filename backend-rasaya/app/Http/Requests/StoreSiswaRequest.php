<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiswaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:50', Rule::unique('users', 'identifier')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['nullable', 'string', 'min:6'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
        ];
    }
}
