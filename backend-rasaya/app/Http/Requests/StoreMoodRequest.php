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
            'gambar' => ['nullable', 'image', 'max:2048'],   // path/URL
            'tanggal' => ['nullable', 'date'], // opsional (default: today)
            'catatan' => ['nullable', 'string'],              // opsional
        ];
    }

    public function attributes(): array
    {
        return [
            'skor' => 'skor emoji',
        ];
    }
}
