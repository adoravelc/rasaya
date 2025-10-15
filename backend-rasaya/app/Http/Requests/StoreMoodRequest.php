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
            'skor' => ['required', 'integer'],
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
}
