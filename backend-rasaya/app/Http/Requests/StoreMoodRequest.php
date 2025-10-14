<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'siswa';
    }

    public function rules(): array
    {
        return [
            'skor' => ['required', 'integer', 'between:1,5'],
            'gambar' => ['nullable', 'string', 'max:255'],   // path/URL
            'tanggal' => ['nullable', 'date'],                // opsional (default: today)
        ];
    }

    public function attributes(): array
    {
        return [
            'skor' => 'skor emoji',
        ];
    }

    public function messages(): array
    {
        return [
            'skor.between' => 'Skor harus 1 sampai 5.',
        ];
    }
}
